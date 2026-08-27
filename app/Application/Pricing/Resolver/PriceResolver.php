<?php

namespace App\Application\Pricing\Resolver;

use App\Application\Pricing\Resolver\PriceSegment;
use App\Application\Pricing\Resolver\ReservationPrice;
use App\Domain\Pricing\Entities\CourtPriceRule;
use App\Domain\Pricing\Exceptions\PriceNotAvailableException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class PriceResolver
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function resolve(
        int $branchId,
        int $tipoCourtId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): ReservationPrice {

        /*
        |--------------------------------------------------------------------------
        | 1. Validar rango
        |--------------------------------------------------------------------------
        */

        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException(
                'La fecha de finalización debe ser posterior a la de inicio.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Buscar precio base
        |--------------------------------------------------------------------------
        */

        $basePrice = $this->prices->findForCourt(
            branchId: $branchId,
            tipoCourtId: $tipoCourtId,
        );

        if (
            $basePrice === null
            || ! $basePrice->isActive()
        ) {
            throw new PriceNotAvailableException();
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Obtener promociones
        |--------------------------------------------------------------------------
        */

        $rules = $this->prices->findActiveRules(
            $basePrice->getId()
        );


        /*
        |--------------------------------------------------------------------------
        | 4. Recorrer la reserva minuto a minuto
        |--------------------------------------------------------------------------
        |
        | Para cada minuto averiguamos qué tarifa corresponde.
        |
        | Después agruparemos los minutos consecutivos que tengan
        | la misma tarifa.
        |
        */

        $rawSegments = [];

        $current = $startsAt;

        while ($current < $endsAt) {

            $next = $current->add(
                new DateInterval('PT1M')
            );

            /*
             * Evitamos pasarnos del final solicitado.
             */
            if ($next > $endsAt) {
                $next = $endsAt;
            }

            $rule = $this->resolveRuleForMoment(
                rules: $rules,
                moment: $current,
            );

            $hourlyPrice = $rule !== null
                ? $rule->getPrice()
                : $basePrice->getPrice();

            $rawSegments[] = [
                'startsAt' => $current,
                'endsAt' => $next,
                'hourlyPrice' => $hourlyPrice,
                'ruleId' => $rule?->getId(),
                'ruleName' => $rule?->getName(),
            ];

            $current = $next;
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Agrupar minutos consecutivos con la misma tarifa
        |--------------------------------------------------------------------------
        */

        $groups = $this->groupSegments(
            $rawSegments
        );


        /*
        |--------------------------------------------------------------------------
        | 6. Calcular subtotal de cada tramo
        |--------------------------------------------------------------------------
        */

        $segments = [];
        $totalCents = 0;

        foreach ($groups as $group) {

            $minutes = (int) (
                ($group['endsAt']->getTimestamp()
                    - $group['startsAt']->getTimestamp())
                / 60
            );

            $hourlyPriceCents = $this->toCents(
                $group['hourlyPrice']
            );

            /*
             * El precio representa 60 minutos.
             *
             * Ejemplo:
             *
             * $18.000 / hora
             *
             * 30 minutos:
             *
             * 18.000 × 30 / 60
             * = 9.000
             */
            $subtotalCents = (int) round(
                ($hourlyPriceCents * $minutes) / 60
            );

            $totalCents += $subtotalCents;

            $segments[] = new PriceSegment(
                startsAt: $group['startsAt'],
                endsAt: $group['endsAt'],
                hourlyPrice: $group['hourlyPrice'],
                subtotal: $this->fromCents($subtotalCents),
                ruleId: $group['ruleId'],
                ruleName: $group['ruleName'],
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 7. Resultado final
        |--------------------------------------------------------------------------
        */

        return new ReservationPrice(
            total: $this->fromCents($totalCents),
            segments: $segments,
        );
    }


    /**
     * De todas las reglas que aplican en un momento concreto,
     * devuelve la de mayor prioridad.
     */
    private function resolveRuleForMoment(
        array $rules,
        DateTimeImmutable $moment,
    ): ?CourtPriceRule {

        $applicableRules = array_filter(
            $rules,
            fn(CourtPriceRule $rule) =>
            $rule->appliesTo($moment)
        );

        if ($applicableRules === []) {
            return null;
        }

        usort(
            $applicableRules,
            fn(
                CourtPriceRule $a,
                CourtPriceRule $b
            ) =>
            $b->getPriority()
                <=>
                $a->getPriority()
        );

        return $applicableRules[0];
    }


    /**
     * Une minutos consecutivos que tengan exactamente
     * la misma tarifa/regla.
     */
    private function groupSegments(array $segments): array
    {
        if ($segments === []) {
            return [];
        }

        $groups = [];

        foreach ($segments as $segment) {

            $lastIndex = array_key_last($groups);

            if ($lastIndex === null) {
                $groups[] = $segment;

                continue;
            }

            $last = $groups[$lastIndex];

            $samePrice =
                $last['hourlyPrice']
                ===
                $segment['hourlyPrice'];

            $sameRule =
                $last['ruleId']
                ===
                $segment['ruleId'];

            $contiguous =
                $last['endsAt']
                ==
                $segment['startsAt'];

            if (
                $samePrice
                && $sameRule
                && $contiguous
            ) {
                $groups[$lastIndex]['endsAt'] =
                    $segment['endsAt'];

                continue;
            }

            $groups[] = $segment;
        }

        return $groups;
    }


    /**
     * Convierte DECIMAL/string a centavos.
     *
     * "18000.00" → 1800000
     *
     * Evitamos realizar cálculos monetarios directamente
     * con float.
     */
    private function toCents(string $amount): int
    {
        $parts = explode('.', $amount);

        $integer = $parts[0];

        $decimal = $parts[1] ?? '00';

        $decimal = str_pad(
            substr($decimal, 0, 2),
            2,
            '0'
        );

        return ((int) $integer * 100)
            + (int) $decimal;
    }


    /**
     * Convierte centavos nuevamente al formato monetario.
     *
     * 1800000 → "18000.00"
     */
    private function fromCents(int $cents): string
    {
        return number_format(
            $cents / 100,
            2,
            '.',
            ''
        );
    }
}

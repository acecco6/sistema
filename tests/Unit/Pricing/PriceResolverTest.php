<?php

namespace Tests\Unit\Pricing;

use App\Application\Pricing\Resolver\PriceResolver;
use App\Domain\Pricing\Entities\CourtPrice;
use App\Domain\Pricing\Entities\CourtPriceRule;
use App\Domain\Pricing\Exceptions\PriceNotAvailableException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PriceResolverTest extends TestCase
{
    public function test_devuelve_precio_base_cuando_no_hay_promociones(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $basePrice = $this->makeBasePrice();

        $repository
            ->expects($this->once())
            ->method('findForCourt')
            ->with(1, 1)
            ->willReturn($basePrice);

        $repository
            ->expects($this->once())
            ->method('findActiveRules')
            ->with(1)
            ->willReturn([]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,
            startsAt: new DateTimeImmutable(
                '2026-09-10 15:00:00'
            ),
            endsAt: new DateTimeImmutable(
                '2026-09-10 16:00:00'
            ),
        );

        $this->assertSame(
            '25000.00',
            $result->total
        );

        $this->assertCount(
            1,
            $result->segments
        );

        $this->assertSame(
            '25000.00',
            $result->segments[0]->subtotal
        );

        $this->assertNull(
            $result->segments[0]->ruleId
        );
    }

    public function test_aplica_promocion_a_toda_la_reserva_si_esta_completamente_dentro_del_horario(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $this->makeRule(
                    price: '18000.00',
                    dayOfWeek: 2,
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                ),
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,

            // Martes
            startsAt: new DateTimeImmutable(
                '2026-09-01 15:00:00'
            ),

            endsAt: new DateTimeImmutable(
                '2026-09-01 16:00:00'
            ),
        );

        $this->assertSame(
            '18000.00',
            $result->total
        );

        $this->assertCount(
            1,
            $result->segments
        );

        $this->assertSame(
            'Happy Hour',
            $result->segments[0]->ruleName
        );
    }

    public function test_reserva_que_empieza_al_final_de_la_promocion_usa_precio_base(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $this->makeRule(
                    price: '18000.00',
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                ),
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,

            startsAt: new DateTimeImmutable(
                '2026-09-10 18:00:00'
            ),

            endsAt: new DateTimeImmutable(
                '2026-09-10 19:00:00'
            ),
        );

        $this->assertSame(
            '25000.00',
            $result->total
        );

        $this->assertNull(
            $result->segments[0]->ruleId
        );
    }

    public function test_divide_reserva_entre_promocion_y_precio_base(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $this->makeRule(
                    price: '18000.00',
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                ),
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,

            startsAt: new DateTimeImmutable(
                '2026-09-10 17:00:00'
            ),

            endsAt: new DateTimeImmutable(
                '2026-09-10 19:00:00'
            ),
        );

        /*
         * 17:00 → 18:00 = $18.000 promo
         * 18:00 → 19:00 = $25.000 normal
         */
        $this->assertSame(
            '43000.00',
            $result->total
        );

        $this->assertCount(
            2,
            $result->segments
        );

        $this->assertSame(
            '18000.00',
            $result->segments[0]->subtotal
        );

        $this->assertSame(
            '25000.00',
            $result->segments[1]->subtotal
        );

        $this->assertSame(
            'Happy Hour',
            $result->segments[0]->ruleName
        );

        $this->assertNull(
            $result->segments[1]->ruleName
        );
    }

    public function test_calcula_proporcionalmente_una_fraccion_de_hora_en_promocion(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $this->makeRule(
                    price: '18000.00',
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                ),
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,

            startsAt: new DateTimeImmutable(
                '2026-09-10 17:30:00'
            ),

            endsAt: new DateTimeImmutable(
                '2026-09-10 19:00:00'
            ),
        );

        /*
         * 17:30 → 18:00
         * 30 minutos promo
         *
         * 18.000 * 30 / 60
         * = 9.000
         *
         * 18:00 → 19:00
         * = 25.000
         *
         * Total = 34.000
         */
        $this->assertSame(
            '34000.00',
            $result->total
        );

        $this->assertCount(
            2,
            $result->segments
        );

        $this->assertSame(
            '9000.00',
            $result->segments[0]->subtotal
        );

        $this->assertSame(
            30,
            $result->segments[0]->minutes()
        );
    }

    public function test_aplica_precio_base_antes_de_promocion_y_promocion_despues(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $this->makeRule(
                    price: '18000.00',
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                ),
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,

            startsAt: new DateTimeImmutable(
                '2026-09-10 13:00:00'
            ),

            endsAt: new DateTimeImmutable(
                '2026-09-10 15:00:00'
            ),
        );

        /*
         * 13 → 14 = $25.000
         * 14 → 15 = $18.000
         */
        $this->assertSame(
            '43000.00',
            $result->total
        );

        $this->assertCount(
            2,
            $result->segments
        );

        $this->assertNull(
            $result->segments[0]->ruleId
        );

        $this->assertNotNull(
            $result->segments[1]->ruleId
        );
    }

    public function test_puede_aplicar_dos_promociones_diferentes_en_la_misma_reserva(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $promoA = $this->makeRule(
            id: 1,
            name: 'Promo A',
            price: '18000.00',
            startTime: '14:00:00',
            endTime: '16:00:00',
        );

        $promoB = $this->makeRule(
            id: 2,
            name: 'Promo B',
            price: '20000.00',
            startTime: '16:00:00',
            endTime: '18:00:00',
        );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $promoA,
                $promoB,
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,

            startsAt: new DateTimeImmutable(
                '2026-09-10 15:00:00'
            ),

            endsAt: new DateTimeImmutable(
                '2026-09-10 19:00:00'
            ),
        );

        /*
         * 15 → 16 = $18.000
         * 16 → 18 = $40.000
         * 18 → 19 = $25.000
         *
         * Total = $83.000
         */
        $this->assertSame(
            '83000.00',
            $result->total
        );

        $this->assertCount(
            3,
            $result->segments
        );

        $this->assertSame(
            'Promo A',
            $result->segments[0]->ruleName
        );

        $this->assertSame(
            'Promo B',
            $result->segments[1]->ruleName
        );

        $this->assertNull(
            $result->segments[2]->ruleName
        );
    }

    public function test_si_dos_promociones_coinciden_gana_la_de_mayor_prioridad(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $this->makeRule(
                    id: 1,
                    name: 'Promo normal',
                    price: '20000.00',
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                    priority: 10,
                ),

                $this->makeRule(
                    id: 2,
                    name: 'Promo especial',
                    price: '15000.00',
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                    priority: 50,
                ),
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,
            startsAt: new DateTimeImmutable(
                '2026-09-10 15:00:00'
            ),
            endsAt: new DateTimeImmutable(
                '2026-09-10 16:00:00'
            ),
        );

        $this->assertSame(
            '15000.00',
            $result->total
        );

        $this->assertSame(
            'Promo especial',
            $result->segments[0]->ruleName
        );
    }

    public function test_lanza_excepcion_si_no_existe_precio_base(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(null);

        $repository
            ->expects($this->never())
            ->method('findActiveRules');

        $resolver = new PriceResolver($repository);

        $this->expectException(
            PriceNotAvailableException::class
        );

        $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,
            startsAt: new DateTimeImmutable(
                '2026-09-10 15:00:00'
            ),
            endsAt: new DateTimeImmutable(
                '2026-09-10 16:00:00'
            ),
        );
    }

    public function test_lanza_excepcion_si_precio_base_esta_inactivo(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice(
                    active: false
                )
            );

        $repository
            ->expects($this->never())
            ->method('findActiveRules');

        $resolver = new PriceResolver($repository);

        $this->expectException(
            PriceNotAvailableException::class
        );

        $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,
            startsAt: new DateTimeImmutable(
                '2026-09-10 15:00:00'
            ),
            endsAt: new DateTimeImmutable(
                '2026-09-10 16:00:00'
            ),
        );
    }

    public function test_no_permite_fecha_final_anterior_o_igual_a_inicio(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        /*
         * Como falla antes de buscar precios,
         * el repository nunca debería utilizarse.
         */
        $repository
            ->expects($this->never())
            ->method('findForCourt');

        $resolver = new PriceResolver($repository);

        $this->expectException(
            InvalidArgumentException::class
        );

        $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,
            startsAt: new DateTimeImmutable(
                '2026-09-10 18:00:00'
            ),
            endsAt: new DateTimeImmutable(
                '2026-09-10 18:00:00'
            ),
        );
    }

    private function makeBasePrice(
        bool $active = true,
    ): CourtPrice {
        return new CourtPrice(
            id: 1,
            branchId: 1,
            tipoCourtId: 1,
            price: '25000.00',
            active: $active,
        );
    }

    private function makeRule(
        int $id = 1,
        string $name = 'Happy Hour',
        string $price = '18000.00',
        ?int $dayOfWeek = null,
        ?string $specificDate = null,
        ?string $startTime = null,
        ?string $endTime = null,
        int $priority = 10,
        ?string $startsAt = null,
        ?string $endsAt = null,
        bool $active = true,
    ): CourtPriceRule {
        return new CourtPriceRule(
            id: $id,
            courtPriceId: 1,
            name: $name,
            price: $price,
            dayOfWeek: $dayOfWeek,
            specificDate: $specificDate,
            startTime: $startTime,
            endTime: $endTime,
            priority: $priority,
            startsAt: $startsAt,
            endsAt: $endsAt,
            active: $active,
        );
    }
    public function test_aplica_promocion_solo_a_la_parte_de_la_reserva_que_entra_en_el_horario(): void
    {
        $repository = $this->createMock(
            CourtPriceRepository::class
        );

        $repository
            ->method('findForCourt')
            ->willReturn(
                $this->makeBasePrice()
            );

        $repository
            ->method('findActiveRules')
            ->willReturn([
                $this->makeRule(
                    price: '18000.00',
                    startTime: '14:00:00',
                    endTime: '18:00:00',
                ),
            ]);

        $resolver = new PriceResolver($repository);

        $result = $resolver->resolve(
            branchId: 1,
            tipoCourtId: 1,
            startsAt: new DateTimeImmutable(
                '2026-09-10 13:30:00'
            ),
            endsAt: new DateTimeImmutable(
                '2026-09-10 14:30:00'
            ),
        );

        $this->assertSame(
            '21500.00',
            $result->total
        );

        $this->assertCount(
            2,
            $result->segments
        );

        $this->assertSame(
            '12500.00',
            $result->segments[0]->subtotal
        );

        $this->assertNull(
            $result->segments[0]->ruleId
        );

        $this->assertSame(
            '9000.00',
            $result->segments[1]->subtotal
        );

        $this->assertSame(
            'Happy Hour',
            $result->segments[1]->ruleName
        );
    }
}

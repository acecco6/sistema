<?php

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Entities\CourtPriceRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CourtPriceRuleTest extends TestCase
{
    public function test_regla_sin_restricciones_aplica_siempre_si_esta_activa(): void
    {
        $rule = $this->makeRule();

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 15:00:00')
            )
        );
    }

    public function test_regla_inactiva_nunca_aplica(): void
    {
        $rule = $this->makeRule(
            active: false,
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 15:00:00')
            )
        );
    }

    public function test_aplica_si_el_dia_de_semana_coincide(): void
    {
        /*
         * 01/09/2026 es martes.
         * ISO:
         * 1 = lunes
         * 2 = martes
         */
        $rule = $this->makeRule(
            dayOfWeek: 2,
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-01 15:00:00')
            )
        );
    }

    public function test_no_aplica_si_el_dia_de_semana_no_coincide(): void
    {
        /*
         * La regla aplica martes.
         * 02/09/2026 es miércoles.
         */
        $rule = $this->makeRule(
            dayOfWeek: 2,
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-02 15:00:00')
            )
        );
    }

    public function test_aplica_si_la_fecha_especifica_coincide(): void
    {
        $rule = $this->makeRule(
            specificDate: '2026-09-10',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 18:00:00')
            )
        );
    }

    public function test_no_aplica_si_la_fecha_especifica_no_coincide(): void
    {
        $rule = $this->makeRule(
            specificDate: '2026-09-10',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-11 18:00:00')
            )
        );
    }

    public function test_aplica_exactamente_en_la_hora_de_inicio(): void
    {
        $rule = $this->makeRule(
            startTime: '14:00:00',
            endTime: '18:00:00',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 14:00:00')
            )
        );
    }

    public function test_aplica_dentro_del_rango_horario(): void
    {
        $rule = $this->makeRule(
            startTime: '14:00:00',
            endTime: '18:00:00',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 16:30:00')
            )
        );
    }

    public function test_no_aplica_antes_de_la_hora_de_inicio(): void
    {
        $rule = $this->makeRule(
            startTime: '14:00:00',
            endTime: '18:00:00',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 13:59:59')
            )
        );
    }

    public function test_no_aplica_exactamente_en_la_hora_de_fin(): void
    {
        $rule = $this->makeRule(
            startTime: '14:00:00',
            endTime: '18:00:00',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 18:00:00')
            )
        );
    }

    public function test_no_aplica_despues_de_la_hora_de_fin(): void
    {
        $rule = $this->makeRule(
            startTime: '14:00:00',
            endTime: '18:00:00',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 19:00:00')
            )
        );
    }

    public function test_aplica_exactamente_al_comienzo_de_la_vigencia(): void
    {
        $rule = $this->makeRule(
            startsAt: '2026-09-01 00:00:00',
            endsAt: '2026-09-30 23:59:59',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-01 00:00:00')
            )
        );
    }

    public function test_aplica_dentro_del_periodo_de_vigencia(): void
    {
        $rule = $this->makeRule(
            startsAt: '2026-09-01 00:00:00',
            endsAt: '2026-09-30 23:59:59',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-15 17:00:00')
            )
        );
    }

    public function test_no_aplica_antes_del_comienzo_de_la_vigencia(): void
    {
        $rule = $this->makeRule(
            startsAt: '2026-09-01 00:00:00',
            endsAt: '2026-09-30 23:59:59',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-08-31 23:59:59')
            )
        );
    }

    public function test_aplica_exactamente_al_final_de_la_vigencia_actual(): void
    {
        /*
         * Con nuestra implementación actual:
         *
         * if ($date > $endsAt)
         *
         * endsAt es INCLUSIVO.
         */
        $rule = $this->makeRule(
            startsAt: '2026-09-01 00:00:00',
            endsAt: '2026-09-30 23:59:59',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-30 23:59:59')
            )
        );
    }

    public function test_no_aplica_despues_del_final_de_la_vigencia(): void
    {
        $rule = $this->makeRule(
            startsAt: '2026-09-01 00:00:00',
            endsAt: '2026-09-30 23:59:59',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-10-01 00:00:00')
            )
        );
    }

    public function test_aplica_cuando_cumple_dia_horario_y_vigencia(): void
    {
        /*
         * La promoción es:
         *
         * - solamente martes
         * - de 14:00 a 18:00
         * - durante septiembre 2026
         *
         * 15/09/2026 es martes.
         */
        $rule = $this->makeRule(
            dayOfWeek: 2,
            startTime: '14:00:00',
            endTime: '18:00:00',
            startsAt: '2026-09-01 00:00:00',
            endsAt: '2026-09-30 23:59:59',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-15 16:00:00')
            )
        );
    }

    public function test_no_aplica_si_cumple_horario_pero_no_dia_de_semana(): void
    {
        /*
         * La regla es martes 14-18.
         * 16/09/2026 es miércoles.
         */
        $rule = $this->makeRule(
            dayOfWeek: 2,
            startTime: '14:00:00',
            endTime: '18:00:00',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-16 16:00:00')
            )
        );
    }

    public function test_no_aplica_si_cumple_dia_y_horario_pero_esta_fuera_de_vigencia(): void
    {
        /*
         * 06/10/2026 es martes,
         * pero la promo terminó en septiembre.
         */
        $rule = $this->makeRule(
            dayOfWeek: 2,
            startTime: '14:00:00',
            endTime: '18:00:00',
            startsAt: '2026-09-01 00:00:00',
            endsAt: '2026-09-30 23:59:59',
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-10-06 16:00:00')
            )
        );
    }

    public function test_fecha_especifica_y_horario_deben_cumplirse_ambos(): void
    {
        $rule = $this->makeRule(
            specificDate: '2026-09-10',
            startTime: '14:00:00',
            endTime: '18:00:00',
        );

        $this->assertTrue(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 16:00:00')
            )
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-10 20:00:00')
            )
        );

        $this->assertFalse(
            $rule->appliesTo(
                new DateTimeImmutable('2026-09-11 16:00:00')
            )
        );
    }

    /**
     * Helper para evitar repetir la creación completa
     * de CourtPriceRule en cada test.
     */
    private function makeRule(
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
            id: 1,
            courtPriceId: 1,
            name: 'Promo Test',
            price: '18000.00',
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
}

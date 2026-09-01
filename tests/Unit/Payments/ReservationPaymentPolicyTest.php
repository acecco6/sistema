<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\Services\ReservationPaymentPolicy;
use PHPUnit\Framework\TestCase;

final class ReservationPaymentPolicyTest extends TestCase
{
    public function test_calcula_el_50_por_ciento_de_senia(): void
    {
        $policy = new ReservationPaymentPolicy();

        $result = $policy->requiredDeposit(
            '40000.00'
        );

        $this->assertSame(
            '20000.00',
            $result
        );
    }

    public function test_considera_cubierta_la_senia_al_llegar_al_50_por_ciento(): void
    {
        $policy = new ReservationPaymentPolicy();

        $result = $policy->isDepositCovered(
            '40000.00',
            '20000.00'
        );

        $this->assertTrue($result);
    }

    public function test_no_considera_cubierta_la_senia_si_falta_dinero(): void
    {
        $policy = new ReservationPaymentPolicy();

        $result = $policy->isDepositCovered(
            '40000.00',
            '19999.99'
        );

        $this->assertFalse($result);
    }

    public function test_acepta_un_monto_superior_al_50_por_ciento(): void
    {
        $policy = new ReservationPaymentPolicy();

        $result = $policy->isDepositCovered(
            '40000.00',
            '25000.00'
        );

        $this->assertTrue($result);
    }
}

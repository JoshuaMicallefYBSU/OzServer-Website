<?php

namespace Tests\Unit;

use App\Support\VatsysCoordinate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VatsysCoordinateTest extends TestCase
{
    /**
     * Sydney (south, east) and Mexico City (north, west), each written in every form.
     *
     * @return array<string, array{string, float, float}>
     */
    public static function coordinates(): array
    {
        return [
            'decimal degrees, south east' => ['-33.9461+151.1772', -33.9461, 151.1772],
            'decimal degrees, north west' => ['+19.4363-099.0721', 19.4363, -99.0721],
            'degrees and decimal minutes, south east' => ['-3356.766+15110.632', -33.9461, 151.1772],
            'degrees and decimal minutes, north west' => ['+1926.178-09904.326', 19.4363, -99.0721],
            'degrees minutes seconds, south east' => ['-335645.960+1511037.920', -33.9461, 151.1772],
            'degrees minutes seconds, north west' => ['+192610.680-0990419.560', 19.4363, -99.0721],
            'degrees minutes seconds without a fraction' => ['-335645+1511038', -33.945833, 151.177222],
            'under one degree keeps its sign' => ['-003000.000+0003000.000', -0.5, 0.5],
        ];
    }

    #[DataProvider('coordinates')]
    public function test_decodes_every_form_in_both_hemispheres(string $raw, float $lat, float $lon): void
    {
        $decoded = VatsysCoordinate::decode($raw);

        $this->assertEqualsWithDelta($lat, $decoded['lat'], 0.00001);
        $this->assertEqualsWithDelta($lon, $decoded['lon'], 0.00001);
    }

    public function test_decodes_a_boundary_in_order(): void
    {
        $points = VatsysCoordinate::decodeBoundary(' -33.9461+151.1772 / +1926.178-09904.326 /');

        $this->assertCount(2, $points);
        $this->assertEqualsWithDelta(-33.9461, $points[0]['lat'], 0.00001);
        $this->assertEqualsWithDelta(-99.0721, $points[1]['lon'], 0.00001);
    }

    public function test_refuses_what_is_not_a_coordinate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VatsysCoordinate::decode('3356S15110E');
    }
}

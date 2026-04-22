<?php

namespace tests\unit\models;

use app\models\Crashreport;
use Codeception\Test\Unit;

/**
 * Pure-function helpers on Crashreport. No DB required.
 */
class CrashreportHelpersTest extends Unit
{
    public function testGeneratorVersionToStrPadsShortInputs(): void
    {
        verify(Crashreport::generatorVersionToStr(1402))->equals('1.4.2');
        verify(Crashreport::generatorVersionToStr(102))->equals('0.1.2');
        verify(Crashreport::generatorVersionToStr(0))->equals('0.0.0');
    }

    public function testGeneratorVersionToStrHandlesNullAndEmpty(): void
    {
        verify(Crashreport::generatorVersionToStr(null))->equals('');
        verify(Crashreport::generatorVersionToStr(''))->equals('');
    }

    public function testGeoIdToCountryNameMapsKnownCodes(): void
    {
        verify(Crashreport::geoIdToCountryName('US'))->equals('United States');
        verify(Crashreport::geoIdToCountryName('us'))->equals('United States');
        verify(Crashreport::geoIdToCountryName('DE'))->equals('Germany');
    }

    public function testGeoIdToCountryNameFallsBackToCode(): void
    {
        verify(Crashreport::geoIdToCountryName('XX'))->equals('XX');
        verify(Crashreport::geoIdToCountryName(''))->equals('');
        verify(Crashreport::geoIdToCountryName(null))->equals('');
    }
}

<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Test\BaseTestCase;
use ReflectionClass;
use Ramsey\Uuid\Uuid;

class ServiceSetOverrideInheritedValuesTest extends BaseTestCase
{
    public function testRequiredServiceSetValuesAreInheritedWhenEditingHostOverrides(): void
    {
        $properties = [
            ['key_name' => 'script', 'required' => true],
            ['key_name' => 'arguments', 'required' => false],
        ];

        $result = $this->mergeRows(
            $properties,
            ['arguments' => '-Verbose'],
            ['script' => 'C:\\Checks\\health.ps1', 'arguments' => '-Quiet'],
            'Service Set: Windows'
        );

        $this->assertSame('C:\\Checks\\health.ps1', $result[0]['inherited']);
        $this->assertSame('Service Set: Windows', $result[0]['inherited_from']);
        $this->assertArrayNotHasKey('value', $result[0]);
        $this->assertSame('-Verbose', $result[1]['value']);
        $this->assertSame('-Quiet', $result[1]['inherited']);
    }

    public function testExistingHostOverrideTakesPrecedenceAndZeroIsRetained(): void
    {
        $result = $this->mergeRows(
            [
                ['key_name' => 'script', 'required' => true],
                ['key_name' => 'retries', 'required' => true],
            ],
            ['script' => 'override.ps1'],
            ['script' => 'default.ps1', 'retries' => '0'],
            'Service Set: Windows'
        );

        $this->assertSame('override.ps1', $result[0]['value']);
        $this->assertSame('default.ps1', $result[0]['inherited']);
        $this->assertSame('0', $result[1]['inherited']);
        $this->assertArrayNotHasKey('value', $result[1]);
    }

    public function testAbsentBaseValueKeepsRequiredFieldEmpty(): void
    {
        $result = $this->mergeRows(
            [['key_name' => 'script', 'required' => true]],
            [],
            [],
            'Service Set: Windows'
        );

        $this->assertArrayNotHasKey('value', $result[0]);
        $this->assertArrayNotHasKey('inherited', $result[0]);
    }

    public function testRequiredFieldReceivesInheritedValueWithoutPersistingAnOverride(): void
    {
        $result = $this->mergeRows(
            [[
                'key_name' => 'script',
                'label' => 'Powershell Script',
                'required' => true,
                'value_type' => 'string',
                'uuid' => Uuid::uuid4()->getBytes(),
            ]],
            [],
            ['script' => 'C:\\Checks\\health.ps1'],
            'Service Set: Windows'
        );

        $formValues = DictionaryItem::prepare($result[0]);
        $this->assertSame('C:\\Checks\\health.ps1', $formValues['inherited']);
        $this->assertSame('', $formValues['var']);
        $this->assertArrayNotHasKey('value', $result[0]);
    }

    public function testInheritedSensitiveServiceSetValueIsNotExposedInTheForm(): void
    {
        $result = $this->mergeRows(
            [[
                'key_name' => 'credential',
                'label' => 'Credential',
                'required' => true,
                'value_type' => 'sensitive',
                'uuid' => Uuid::uuid4()->getBytes(),
            ]],
            [],
            ['credential' => 'secret-from-set'],
            'Service Set: Windows'
        );

        $formValues = DictionaryItem::prepare($result[0]);
        $this->assertSame('1', $formValues['inherited']);
        $this->assertSame('', $formValues['var']);
        $this->assertStringNotContainsString('secret-from-set', json_encode($formValues));
    }

    private function mergeRows(array $properties, array $overrides, array $base, string $origin): array
    {
        $controller = (new ReflectionClass(HostController::class))->newInstanceWithoutConstructor();

        return self::callMethod($controller, 'mergeServiceSetOverrideValues', [
            $properties, $overrides, $base, $origin
        ]);
    }
}

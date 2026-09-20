<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class ServiceSetRequiredVarsFormTest extends BaseTestCase
{
    /**
     * Use the same form preparation as HostController::servicesetserviceAction().
     * The variable is present on the service in the set but has no per-host override.
     */
    public function testRequiredServiceSetValueIsInheritedWhenEditingHostOverride(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $service = IcingaService::create([
            'object_name' => '___TEST___3117_set_service',
            'object_type' => 'object',
            'vars.script' => 'existing script',
        ], $db);
        $host = IcingaHost::create([
            'object_name' => '___TEST___3117_host',
            'object_type' => 'object',
        ], $db);

        $controller = $this->getMockBuilder(HostController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjectCustomProperties', 'hasPermission'])
            ->getMock();

        $controller->method('getObjectCustomProperties')->willReturn([[
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'script',
            'value_type' => 'string',
            'label' => 'Powershell Script',
            'required' => true,
            'allow_removal' => false,
        ]]);
        $controller->method('hasPermission')->willReturn(false);

        $form = $controller->prepareCustomPropertiesForm($service, $host);
        $form->setHostForService($host);

        $form->ensureAssembled();
        $html = (string) $form;
        $this->assertStringContainsString(
            'existing script',
            $html,
            'The existing service-set value must be shown as inherited, not as an empty required field'
        );
        $this->assertStringContainsString('Powershell Script', $html);

        // Merely viewing a default must not create a host-level override.
        $this->assertSame([], (array) $host->getOverriddenServiceVars($service->getObjectName()));
    }

    public function testHostOverrideWinsOverServiceSetDefault(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $service = IcingaService::create([
            'object_name' => '___TEST___3117_override_service',
            'object_type' => 'object',
            'vars.script' => 'service-set default',
        ], $db);
        $host = IcingaHost::create([
            'object_name' => '___TEST___3117_override_host',
            'object_type' => 'object',
        ], $db);
        $host->overrideServiceVars($service->getObjectName(), (object) [
            'script' => 'host-specific script',
        ]);

        $controller = $this->getMockBuilder(HostController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjectCustomProperties', 'hasPermission'])
            ->getMock();
        $controller->method('getObjectCustomProperties')->willReturn([[
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'script',
            'value_type' => 'string',
            'label' => 'Powershell Script',
            'required' => true,
            'allow_removal' => false,
        ]]);
        $controller->method('hasPermission')->willReturn(false);

        $form = $controller->prepareCustomPropertiesForm($service, $host);
        $form->setHostForService($host);
        $form->ensureAssembled();
        $html = (string) $form;
        $this->assertStringContainsString('host-specific script', $html);
        $this->assertStringContainsString('service-set default', $html);
    }
}

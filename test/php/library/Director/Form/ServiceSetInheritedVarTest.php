<?php

// SPDX-FileCopyrightText: 2026 Daniel Vedovato <https://github.com/DanielVdWP>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Test\BaseTestCase;
use Zend_Form_Element_Text;

class ServiceSetInheritedVarTest extends BaseTestCase
{
    public function testRequiredFieldProvidedByServiceSetDoesNotBlockHostServiceEdit(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = IcingaHost::create([
            'object_name' => '___TEST___issue3117-host',
            'object_type' => 'object',
        ], $db);
        $set = IcingaServiceSet::create([
            'object_name' => '___TEST___issue3117-set',
            'object_type' => 'template',
            'vars' => (object) ['powershell_script' => 'Get-Date'],
        ], $db);
        $service = IcingaService::create([
            'object_name' => '___TEST___issue3117-service',
            'object_type' => 'object',
        ], $db);

        // Reproduce the Host -> Service Set -> Service "Modify" form context.
        // The variable is provided by the set, not by a host-level override.
        $form = IcingaServiceForm::load()->setDb($db);
        $form->setHost($host);
        $form->setServiceSet($set);
        $form->setObject($service);

        $field = DirectorDatafield::create(['varname' => 'powershell_script'], $db);
        $element = new Zend_Form_Element_Text('var_powershell_script');
        $element->setRequired(true);

        self::callMethod($field, 'applyObjectData', [$element, $form]);

        $this->assertFalse(
            $element->isRequired(),
            'A required custom field already provided by the Service Set must not require a host override'
        );
        $this->assertStringContainsString(
            'Get-Date',
            (string) $element->getAttrib('placeholder'),
            'The Service Set value must be visible as the inherited field value'
        );
        $this->assertSame(
            (object) [],
            $host->getOverriddenServiceVars($service->getObjectName()),
            'Displaying an inherited value must not create a host-level override'
        );
    }
}

<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Application\Config;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use ReflectionClass;

require_once __DIR__ . '/ServiceSetOverrideTestController.php';

/**
 * Exercise the real Director host override form with stored Host, Service Set,
 * member and required property, using the CI's Icinga Web 2 and Director DB.
 */
class ServiceSetHostOverrideIntegrationTest extends BaseTestCase
{
    public function testRequiredValueFromServiceSetIsPresentInTheHostOverrideForm(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
        $suffix = substr(Uuid::uuid4()->toString(), 0, 8);
        $key = '___TEST___3117_script_' . $suffix;
        $host = IcingaHost::create([
            'object_name' => '___TEST___3117_host_' . $suffix,
            'object_type' => 'object',
        ], $db);
        $set = IcingaServiceSet::create([
            'object_name' => '___TEST___3117_set_' . $suffix,
            'object_type' => 'template',
            'vars' => [$key => 'C:\\Checks\\health.ps1'],
        ], $db);
        $member = null;
        $property = null;

        try {
            $host->store();
            $set->store();
            $member = IcingaService::create([
                'object_name' => '___TEST___3117_service_' . $suffix,
                'object_type' => 'apply',
                'service_set_id' => $set->get('id'),
            ], $db);
            $member->store();

            $property = DirectorProperty::create([
                'uuid' => Uuid::uuid4()->getBytes(),
                'key_name' => $key,
                'label' => 'Powershell Script',
                'value_type' => 'string',
            ], $db);
            $property->store();

            $adapter = $db->getDbAdapter();
            $adapter->insert('icinga_service_property', [
                'service_uuid' => DbUtil::quoteBinaryCompat($member->get('uuid'), $adapter),
                'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $adapter),
                'required' => 'y',
            ]);

            $controller = (new ReflectionClass(ServiceSetOverrideTestController::class))
                ->newInstanceWithoutConstructor();
            $controller->formProperties = [[
                'key_name' => $key,
                'uuid' => $property->get('uuid'),
                'value_type' => 'string',
                'label' => 'Powershell Script',
                'required' => true,
                'allow_removal' => false,
            ]];

            $form = $controller->prepareCustomPropertiesForm($member, $host, [], [], $set);
            $form->setServiceSet($set)->setHostForService($host);
            $form->ensureAssembled();
            $dictionary = $form->getElement('properties');
            /** @var DictionaryItem $item */
            $item = $dictionary->getElement('0');
            $this->assertSame(
                'C:\\Checks\\health.ps1',
                $item->getElement('inherited')->getValue(),
                'The Service Set value must be inherited by the actual host override form'
            );
            $this->assertFalse(
                $item->getElement('var')->isRequired(),
                'An inherited value must satisfy the required field without a host override'
            );
            $this->assertEmpty((array) $host->getOverriddenServiceVars($member->getObjectName()));
        } finally {
            if ($member !== null && $member->hasBeenLoadedFromDb()) {
                $member->delete();
            }
            if ($property !== null && $property->hasBeenLoadedFromDb()) {
                $property->delete();
            }
            if ($set->hasBeenLoadedFromDb()) {
                $set->delete();
            }
            if ($host->hasBeenLoadedFromDb()) {
                $host->delete();
            }
        }
    }
}

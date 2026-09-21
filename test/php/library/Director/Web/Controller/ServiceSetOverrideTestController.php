<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\Objects\IcingaObject;

class ServiceSetOverrideTestController extends HostController
{
    public array $formProperties = [];

    protected function getObjectCustomProperties(
        IcingaObject $object,
        bool $isOverrideVars = false,
        array $addedVarUuids = [],
        array $requiredVarUuids = []
    ): array {
        return $this->formProperties;
    }
}

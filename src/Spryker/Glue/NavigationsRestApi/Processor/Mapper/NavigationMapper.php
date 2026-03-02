<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\NavigationsRestApi\Processor\Mapper;

use Generated\Shared\Transfer\NavigationStorageTransfer;
use Generated\Shared\Transfer\RestNavigationAttributesTransfer;

class NavigationMapper implements NavigationMapperInterface
{
    public function mapNavigationStorageTransferToRestNavigationAttributesTransfer(
        NavigationStorageTransfer $navigationStorageTransfer,
        RestNavigationAttributesTransfer $restNavigationAttributesTransfer
    ): RestNavigationAttributesTransfer {
        return $restNavigationAttributesTransfer->fromArray($navigationStorageTransfer->toArray(), true);
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\NavigationsRestApi\Api\Storefront\Provider;

use Generated\Api\Storefront\NavigationsStorefrontResource;
use Generated\Shared\Transfer\NavigationStorageTransfer;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\State\Provider\AbstractStorefrontProvider;
use Spryker\Client\NavigationStorage\NavigationStorageClientInterface;
use Spryker\Client\UrlStorage\UrlStorageClientInterface;
use Spryker\Glue\NavigationsRestApi\NavigationsRestApiConfig;
use Spryker\Service\Serializer\SerializerServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class NavigationsStorefrontProvider extends AbstractStorefrontProvider
{
    protected const string ERROR_CODE_NAVIGATION_NOT_FOUND = '1601';

    protected const string ERROR_CODE_NAVIGATION_ID_NOT_SPECIFIED = '1602';

    protected const string ERROR_MESSAGE_NAVIGATION_NOT_FOUND = 'Navigation not found.';

    protected const string ERROR_MESSAGE_NAVIGATION_ID_NOT_SPECIFIED = 'Navigation id not specified.';

    public function __construct(
        protected NavigationStorageClientInterface $navigationStorageClient,
        protected UrlStorageClientInterface $urlStorageClient,
        protected NavigationsRestApiConfig $navigationsRestApiConfig,
        protected SerializerServiceInterface $serializer,
    ) {
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    protected function provideCollection(): array|null
    {
        throw new GlueApiException(Response::HTTP_BAD_REQUEST, static::ERROR_CODE_NAVIGATION_ID_NOT_SPECIFIED, static::ERROR_MESSAGE_NAVIGATION_ID_NOT_SPECIFIED);
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\GlueApiException
     */
    protected function provideItem(): object|null
    {
        $navigationKey = $this->getUriVariables()['navigationId'] ?? null;

        if ($navigationKey === null) {
            throw new GlueApiException(Response::HTTP_BAD_REQUEST, static::ERROR_CODE_NAVIGATION_ID_NOT_SPECIFIED, static::ERROR_MESSAGE_NAVIGATION_ID_NOT_SPECIFIED);
        }

        $navigationStorageTransfer = $this->navigationStorageClient->findNavigationTreeByKey($navigationKey, $this->getLocale()->getLocaleNameOrFail());

        if ($navigationStorageTransfer === null || $navigationStorageTransfer->getKey() === null) {
            throw new GlueApiException(Response::HTTP_NOT_FOUND, static::ERROR_CODE_NAVIGATION_NOT_FOUND, static::ERROR_MESSAGE_NAVIGATION_NOT_FOUND);
        }

        $navigationData = $navigationStorageTransfer->toArray(true, true);
        $urlCollection = $this->collectNodeUrls($navigationData['nodes'] ?? []);
        $urlStorageTransfers = $urlCollection !== []
            ? $this->urlStorageClient->getUrlStorageTransferByUrls($urlCollection)
            : [];

        return $this->serializer->denormalize(
            $this->prepareResourceData($navigationStorageTransfer, $urlStorageTransfers),
            NavigationsStorefrontResource::class,
        );
    }

    /**
     * @param array<\Generated\Shared\Transfer\UrlStorageTransfer> $urlStorageTransfers
     *
     * @return array<string, mixed>
     */
    protected function prepareResourceData(NavigationStorageTransfer $navigationStorageTransfer, array $urlStorageTransfers): array
    {
        $navigationData = $navigationStorageTransfer->toArray(true, true);

        return [
            'navigationId' => $navigationStorageTransfer->getKey(),
            'name' => $navigationData['name'] ?? null,
            'isActive' => $navigationData['isActive'] ?? null,
            'nodes' => $this->mapNodes(
                $navigationData['nodes'] ?? [],
                $urlStorageTransfers,
                $this->navigationsRestApiConfig->getNavigationTypeToUrlResourceIdFieldMapping(),
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<\Generated\Shared\Transfer\UrlStorageTransfer> $urlStorageTransfers
     * @param array<string, string> $navigationTypeToUrlResourceIdFieldMapping
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mapNodes(
        array $nodes,
        array $urlStorageTransfers,
        array $navigationTypeToUrlResourceIdFieldMapping,
    ): array {
        $mappedNodes = [];

        foreach ($nodes as $node) {
            $mappedNodes[] = [
                'isActive' => $node['isActive'] ?? null,
                'resourceId' => $this->findResourceId($node, $urlStorageTransfers, $navigationTypeToUrlResourceIdFieldMapping),
                'nodeType' => $node['nodeType'] ?? null,
                'title' => $node['title'] ?? null,
                'url' => $node['url'] ?? null,
                'cssClass' => $node['cssClass'] ?? null,
                'validFrom' => $node['validFrom'] ?? null,
                'validTo' => $node['validTo'] ?? null,
                'children' => $this->mapNodes(
                    $node['children'] ?? [],
                    $urlStorageTransfers,
                    $navigationTypeToUrlResourceIdFieldMapping,
                ),
            ];
        }

        return $mappedNodes;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<\Generated\Shared\Transfer\UrlStorageTransfer> $urlStorageTransfers
     * @param array<string, string> $navigationTypeToUrlResourceIdFieldMapping
     */
    protected function findResourceId(
        array $node,
        array $urlStorageTransfers,
        array $navigationTypeToUrlResourceIdFieldMapping,
    ): ?int {
        $nodeUrl = $node['url'] ?? null;
        $nodeType = $node['nodeType'] ?? null;

        if ($nodeUrl === null || $nodeType === null) {
            return null;
        }

        if (!isset($urlStorageTransfers[$nodeUrl], $navigationTypeToUrlResourceIdFieldMapping[$nodeType])) {
            return null;
        }

        return $urlStorageTransfers[$nodeUrl][$navigationTypeToUrlResourceIdFieldMapping[$nodeType]] ?? null;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     *
     * @return array<string>
     */
    protected function collectNodeUrls(array $nodes): array
    {
        $urls = [];

        foreach ($nodes as $node) {
            $url = $node['url'] ?? null;

            if ($url !== null && $url !== '') {
                $urls[] = $url;
            }

            if (isset($node['children'])) {
                $urls = array_merge($urls, $this->collectNodeUrls($node['children']));
            }
        }

        return array_unique($urls);
    }
}

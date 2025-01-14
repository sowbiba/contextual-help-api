<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Help\PrestaShop;

use Exception;
use Monolog\Logger;

class PageInfosBuilder
{
    private const FALLBACK_VERSION = '1.6';
    private const FALLBACK_VERSION_MAPPING_FILENAME = 'mapping16.php';
    private const FALLBACK_CONTROLLER = 'GettingStarted';
    private const FALLBACK_LANGUAGE = 'en';
    private string $mappingFilesPath;

    public function __construct(
        string $mappingFilesPath,
        private Logger $logger
    ) {
        $this->mappingFilesPath = $mappingFilesPath;
    }

    /**
     * Returns a PageInfos object containing the page ID, the controller and the language depending on the URL parameters
     *
     * @param string $uri
     * @param string|null $version
     *
     * @return PageInfos
     *
     * @throws Exception
     */
    public function getPageInfosFromUri(string $uri, string | null $version): PageInfos
    {
        try {
            $urlComponents = parse_url($uri);
            if (!is_array($urlComponents) || !isset($urlComponents['path'])) {
                throw new Exception('Invalid URL');
            }
            $pathElements = explode('/', trim($urlComponents['path'], '/'));

            if (count($pathElements) < 3) {
                throw new Exception('Invalid URL' . $uri);
            }

            $language = $pathElements[0];
            $controller = $pathElements[2];

            $version = $this->getVersion($version);

            $mapping = include $this->getMappingFilename($version);

            if (!isset($mapping[$controller])) {
                $controller = self::FALLBACK_CONTROLLER;
            }
            if (!isset($mapping[$controller][$language])) {
                $language = self::FALLBACK_LANGUAGE;
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            throw new Exception('Cannot load page infos');
        }

        return new PageInfos(
            (int) $mapping[$controller][$language],
            $controller,
            $language
        );
    }

    /**
     * Remove string part of the version given
     * And if the version if lower than the FALLBACK_VERSION, take the FALLBACK_VERSION
     *
     * @param string|null $version
     *
     * @return string
     */
    private function getVersion(string | null $version): string
    {
        if (null === $version) {
            return self::FALLBACK_VERSION;
        }

        $version = $this->sanitizeVersion($version);

        // Prevent too old versions
        if (version_compare($version, self::FALLBACK_VERSION, '<')) {
            return self::FALLBACK_VERSION;
        }

        return $version;
    }

    /**
     * Sanitizes a version number string so we can trust it.
     *
     * Any non-numeric, non-dot character will be trimmed off.
     *
     * @param string $version The version number string to sanitize
     *
     * @return string the sanitized version number string
     */
    private function sanitizeVersion(string $version): string
    {
        $clean = '';
        $version = preg_replace('@[^0-9.]@', '', $version);

        if (preg_match('#^\d+(\.\d+)*#', (string) $version, $match)) {
            $clean = $match[0];
        }

        return $clean;
    }

    /**
     * Returns a mapping filename depending on the provided version.
     *
     * Warning, the returned filename depends on the provided version string only. This file might not exist.
     *
     * @param string $version The version from which we need mapping filename
     *
     * @return string The mapping filename
     */
    private function getMappingFilename(string $version): string
    {
        $versionNumbers = explode('.', $version);
        $fv = $versionNumbers[0] . ($versionNumbers[1] ?? '');

        $mappingFilename = "mapping$fv.php";

        $mappingsFilePath = rtrim($this->mappingFilesPath, '/') . '/';
        /*
         * If the version mapping file exists, we take it
         * Otherwise, if a mapping file for 1.6 exists, we take it
         * If none of the 2 exist, we take the mapping file for 1.7
         */
        if (file_exists($mappingsFilePath . $mappingFilename)) {
            return $mappingsFilePath . $mappingFilename;
        }

        if (file_exists($mappingsFilePath . self::FALLBACK_VERSION_MAPPING_FILENAME)) {
            return $mappingsFilePath . self::FALLBACK_VERSION_MAPPING_FILENAME;
        }

        // Extra security when fallback mapping file is missing
        return $mappingsFilePath . 'mapping17.php';
    }
}

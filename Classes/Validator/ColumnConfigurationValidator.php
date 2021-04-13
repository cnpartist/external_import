<?php

declare(strict_types=1);

namespace Cobweb\ExternalImport\Validator;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Cobweb\ExternalImport\Domain\Model\Configuration;
use Cobweb\ExternalImport\Step\TransformDataStep;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class parses the "column" part of an External Import configuration
 * and reports errors and other glitches.
 *
 * NOTE: this is not a strict Extbase Validator.
 *
 * @package Cobweb\ExternalImport\Validator
 */
class ColumnConfigurationValidator
{
    /**
     * @var string[] List of properties allowed for substructureFields when handling array-type data
     */
    protected static $substructurePropertiesForArrayType = ['field', 'arrayPath', 'arrayPathSeparator'];

    /**
     * @var string[] List of properties allowed for substructureFields when handling XML-type data
     */
    protected static $substructurePropertiesForXmlType = [
        'field',
        'fieldNS',
        'attribute',
        'attributeNS',
        'xpath',
        'xmlValue'
    ];

    /**
     * @var ValidationResult
     */
    protected $results;

    public function __construct(ValidationResult $result)
    {
        $this->results = $result;
    }

    /**
     * Validates the given configuration.
     *
     * @param Configuration $configuration Configuration object to check
     * @param string $column Name of the column to check
     * @return bool
     */
    public function isValid(Configuration $configuration, string $column): bool
    {
        $columnConfiguration = $configuration->getConfigurationForColumn($column);
        // This method is generally called in a loop over all columns, make sure to reset the results between each validation
        $this->results->reset();
        // Validate properties used to choose the import value
        $this->validateDataSettingProperties(
            $configuration->getGeneralConfiguration(),
            $columnConfiguration
        );
        // Validate children configuration
        if (isset($columnConfiguration['children'])) {
            $this->validateChildrenProperty($columnConfiguration['children']);
        }
        // Validate substructureFields configuration
        if (isset($columnConfiguration['substructureFields'])) {
            $this->validateSubstructureFieldsProperty(
                $configuration->getGeneralConfiguration(),
                $columnConfiguration['substructureFields']
            );
        }
        // Return the global validation result
        // Consider that the configuration does not validate if there's at least one error or one warning
        return $this->results->countForSeverity(AbstractMessage::ERROR) +
            $this->results->countForSeverity(AbstractMessage::WARNING) === 0;
    }

    /**
     * Validates that the column configuration contains the appropriate properties for
     * choosing the value to import, depending on the data type (array or XML).
     *
     * The "value" property has a particular influence on the import process. It is used to set a fixed value.
     * This means that any data-setting property will in effect be overridden by the "value" property
     * even if the "value" property is considered to be a transformation property.
     * Users should be made aware of such potential conflicts.
     *
     * @param array $generalConfiguration General configuration to check
     * @param array $columnConfiguration Column configuration to check (unused when checking a "ctrl" configuration)
     */
    public function validateDataSettingProperties(array $generalConfiguration, array $columnConfiguration): void
    {
        $hasValueProperty = $this->hasValueProperty($columnConfiguration);
        if ($generalConfiguration['data'] === 'array') {
            // For data of type "array", either a "field", "value" or a "arrayPath" property are needed
            if (!$hasValueProperty && !isset($columnConfiguration['field']) && !isset($columnConfiguration['arrayPath'])) {
                // NOTE: validation result is arbitrarily added to the "field" property
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:missingPropertiesForArrayData',
                        'external_import'
                    ),
                    AbstractMessage::ERROR
                );
                // "value" property should not be set if another value-setting property is also defined, except in special cases, so let's issue a notice
            } elseif ($hasValueProperty && isset($columnConfiguration['field'])) {
                // NOTE: validation result is arbitrarily added to the "field" property
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:conflictingPropertiesForArrayData',
                        'external_import'
                    ),
                    AbstractMessage::NOTICE
                );
            }
        } elseif ($generalConfiguration['data'] === 'xml') {
            // It is okay to have no configuration for a column. Just make sure this is really what the user wanted.
            if (!$hasValueProperty && !isset($columnConfiguration['field']) && !isset($columnConfiguration['attribute']) && !isset($columnConfiguration['xpath'])) {
                // NOTE: validation result is arbitrarily added to the "field" property
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:missingPropertiesForXmlData',
                        'external_import'
                    ),
                    AbstractMessage::NOTICE
                );
                // "value" property should not be set if another value-setting property is also defined
            } elseif (
                $hasValueProperty
                && (isset($columnConfiguration['field']) || isset($columnConfiguration['attribute']) || isset($columnConfiguration['xpath']))
            ) {
                // NOTE: validation result is arbitrarily added to the "field" property
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:conflictingPropertiesForXmlData',
                        'external_import'
                    ),
                    AbstractMessage::NOTICE
                );
            }
        }
    }

    /**
     * Validates the "children" property.
     *
     * @param mixed $childrenConfiguration
     */
    public function validateChildrenProperty($childrenConfiguration): void
    {
        // Issue error right away if structure is not an array
        if (!is_array($childrenConfiguration)) {
            // NOTE: validation result is arbitrarily added to the "field" property
            $this->results->add(
                'field',
                LocalizationUtility::translate(
                    'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyIsNotAnArray',
                    'external_import'
                ),
                AbstractMessage::ERROR
            );
            // There's nothing else to check
            return;
        }
        // Check the existence of the "table" property
        if (!array_key_exists('table', $childrenConfiguration)) {
            $this->results->add(
                'field',
                LocalizationUtility::translate(
                    'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyMissingTableInformation',
                    'external_import'
                ),
                AbstractMessage::ERROR
            );
        }
        // Check the existence of the "columns" property
        $columns = [];
        if (!array_key_exists('columns', $childrenConfiguration)) {
            $this->results->add(
                'field',
                LocalizationUtility::translate(
                    'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyMissingColumnsInformation',
                    'external_import'
                ),
                AbstractMessage::ERROR
            );
            // If it exists check that individual configuration uses only "value" and "field" sub-properties
        } else {
            $columns = array_keys($childrenConfiguration['columns']);
            foreach ($childrenConfiguration['columns'] as $column) {
                if (is_array($column)) {
                    $key = key($column);
                    if ($key !== 'value' && $key !== 'field') {
                        $this->results->add(
                            'field',
                            LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyColumnsInformationWrongSubproperties',
                                'external_import',
                                [$key]
                            ),
                            AbstractMessage::ERROR
                        );
                    }
                } else {
                    $this->results->add(
                        'field',
                        LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyColumnsInformationNotAnArray',
                            'external_import'
                        ),
                        AbstractMessage::ERROR
                    );
                }
            }
        }
        // Check the "controlColumnsForUpdate" property
        if (array_key_exists('controlColumnsForUpdate', $childrenConfiguration)) {
            $controlColumns = GeneralUtility::trimExplode(',', $childrenConfiguration['controlColumnsForUpdate']);
            if (count($controlColumns) > 0) {
                $missingColumns = array_diff($controlColumns, $columns);
                if (count($missingColumns) > 0) {
                    $this->results->add(
                        'field',
                        LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyControlColumnsForUpdateContainsInvalidColumns',
                            'external_import',
                            [
                                implode(', ', $missingColumns)
                            ]
                        ),
                        AbstractMessage::ERROR
                    );
                }
            } else {
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyControlColumnsForUpdateMissing',
                        'external_import'
                    ),
                    AbstractMessage::NOTICE
                );
            }
        } else {
            $this->results->add(
                'field',
                LocalizationUtility::translate(
                    'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyControlColumnsForUpdateMissing',
                    'external_import'
                ),
                AbstractMessage::NOTICE
            );
        }
        // Check the "controlColumnsForDelete" property
        if (array_key_exists('controlColumnsForDelete', $childrenConfiguration)) {
            $controlColumns = GeneralUtility::trimExplode(',', $childrenConfiguration['controlColumnsForDelete']);
            if (count($controlColumns) > 0) {
                $missingColumns = array_diff($controlColumns, $columns);
                if (count($missingColumns) > 0) {
                    $this->results->add(
                        'field',
                        LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyControlColumnsForDeleteContainsInvalidColumns',
                            'external_import',
                            [
                                implode(', ', $missingColumns)
                            ]
                        ),
                        AbstractMessage::ERROR
                    );
                }
            } else {
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyControlColumnsForDeleteMissing',
                        'external_import'
                    ),
                    AbstractMessage::NOTICE
                );
            }
            if (!array_key_exists('controlColumnsForUpdate', $childrenConfiguration)) {
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyControlColumnsForDeleteSetButNotControlColumnsForUpdate',
                        'external_import'
                    ),
                    AbstractMessage::NOTICE
                );
            }
        } else {
            $this->results->add(
                'field',
                LocalizationUtility::translate(
                    'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:childrenProperyControlColumnsForDeleteMissing',
                    'external_import'
                ),
                AbstractMessage::NOTICE
            );
        }
    }

    /**
     * Validates the "substructureField" property.
     *
     * @param array $generalConfiguration External Import general configuration
     * @param array $property substructureFields configuration
     */
    public function validateSubstructureFieldsProperty(array $generalConfiguration, array $property): void
    {
        // Check that the configuration for each field is itself an array
        foreach ($property as $field => $configuration) {
            if (!is_array($configuration)) {
                $this->results->add(
                    'field',
                    LocalizationUtility::translate(
                        'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:substructureFieldsPropertyNotAnArrayForField',
                        'external_import',
                        [
                            $field
                        ]
                    ),
                    AbstractMessage::ERROR
                );
            }
        }
        // Check that valid properties are used for each field, depending on the overall data type
        if ($generalConfiguration['data'] === 'array') {
            foreach ($property as $field => $configuration) {
                // Empty configuration is not allowed for array-type data
                if (!is_array($configuration) || count($configuration) === 0) {
                    $this->results->add(
                        'field',
                        LocalizationUtility::translate(
                            'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:substructureFieldsPropertyWithEmptyConfigurationForArrayTypeData',
                            'external_import',
                            [
                                $property
                            ]
                        ),
                        AbstractMessage::ERROR
                    );
                } else {
                    // Check that all keys match the allowed properties
                    $keys = array_keys($configuration);
                    $wrongKeys = array_diff($keys, self::$substructurePropertiesForArrayType);
                    if (count($wrongKeys) > 0) {
                        $this->results->add(
                            'field',
                            LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:substructureFieldsPropertyWithWrongConfigurationForArrayTypeData',
                                'external_import',
                                [
                                    implode(', ', $wrongKeys),
                                    implode(', ', self::$substructurePropertiesForArrayType)
                                ]
                            ),
                            AbstractMessage::ERROR
                        );
                    }
                }
            }
        } else {
            foreach ($property as $field => $configuration) {
                // An empty configuration is okay for XML-type data
                if (count($configuration) > 0) {
                    // Check that all keys match the allowed properties
                    $keys = array_keys($configuration);
                    $wrongKeys = array_diff($keys, self::$substructurePropertiesForXmlType);
                    if (count($wrongKeys) > 0) {
                        $this->results->add(
                            'field',
                            LocalizationUtility::translate(
                                'LLL:EXT:external_import/Resources/Private/Language/Validator.xlf:substructureFieldsPropertyWithWrongConfigurationForXmlTypeData',
                                'external_import',
                                [
                                    implode(', ', $wrongKeys),
                                    implode(', ', self::$substructurePropertiesForXmlType)
                                ]
                            ),
                            AbstractMessage::ERROR
                        );
                    }
                }
            }
        }
    }

    /**
     * Checks if the "transformations" properties contains the "value" property.
     *
     * @param array $columnConfiguration
     * @return bool
     */
    public function hasValueProperty(array $columnConfiguration): bool
    {
        if (isset($columnConfiguration['transformations'])) {
            foreach ($columnConfiguration['transformations'] as $transformation) {
                if (array_key_exists('value', $transformation)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns all validation results.
     *
     * @return ValidationResult
     */
    public function getResults(): ValidationResult
    {
        return $this->results;
    }

}
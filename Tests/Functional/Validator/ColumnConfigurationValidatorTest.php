<?php
namespace Cobweb\ExternalImport\Tests\Functional\Validator;

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
use Cobweb\ExternalImport\Validator\ColumnConfigurationValidator;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class ColumnConfigurationValidatorTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
            'typo3conf/ext/external_import'
    ];

    /**
     * @var ColumnConfigurationValidator
     */
    protected $subject;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    public function setUp()
    {
        parent::setUp();
        // Connector services need a global LanguageService object
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
        $GLOBALS['LANG']->init('en');

        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->subject = $this->objectManager->get(ColumnConfigurationValidator::class);
    }

    public function validConfigurationProvider(): array
    {
        return [
                'Data type "array": using property "field" (string)' => [
                        [
                                'data' => 'array',
                        ],
                        [
                                'col' => [
                                        'field' => 'foo'
                                ]
                        ]
                ],
                'Data type "array": using property "field" (positive integer)' => [
                        [
                                'data' => 'array',
                        ],
                        [
                                'col' => [
                                        'field' => 42
                                ]
                        ]
                ],
                'Data type "array": using property "field" (zero)' => [
                        [
                                'data' => 'array',
                        ],
                        [
                                'col' => [
                                        'field' => 0
                                ]
                        ]
                ],
                'Data type "array": using property "value" (number)' => [
                        [
                                'data' => 'array',
                        ],
                        [
                                'col' => [
                                        'transformations' => [
                                                10 => [
                                                        'value' => 17
                                                ]
                                        ]
                                ]
                        ]
                ],
                'Data type "array": using property "value" (string)' => [
                        [
                                'data' => 'array',
                        ],
                        [
                                'col' => [
                                        'transformations' => [
                                                10 => [
                                                        'value' => 'bar'
                                                ]
                                        ]
                                ]
                        ]
                ],
                'Data type "array": using property "arrayPath"' => [
                        [
                                'data' => 'array',
                        ],
                        [
                                'col' => [
                                        'arrayPath' => 'foo/bar'
                                ]
                        ]
                ],
                'Data type "xml": using property "field" (string)' => [
                        [
                                'data' => 'xml',
                        ],
                        [
                                'col' => [
                                        'field' => 'foo'
                                ]
                        ]
                ],
                'Data type "xml": using property "value" (number)' => [
                        [
                                'data' => 'xml',
                        ],
                        [
                                'col' => [
                                        'value' => 17
                                ]
                        ]
                ],
                'Data type "xml": using property "value" (string)' => [
                        [
                                'data' => 'xml',
                        ],
                        [
                                'col' => [
                                        'value' => 'bar'
                                ]
                        ]
                ],
                'Data type "xml": using property "attribute" (string)' => [
                        [
                                'data' => 'xml',
                        ],
                        [
                                'col' => [
                                        'field' => 'baz'
                                ]
                        ]
                ],
                'Data type "xml": using property "xpath" (string)' => [
                        [
                                'data' => 'xml',
                        ],
                        [
                                'col' => [
                                        'field' => 'hello'
                                ]
                        ]
                ],
                'Children definition' => [
                        // No need for a general configuration
                        [],
                        [
                                'col' => [
                                        'children' => [
                                                'table' => 'foo',
                                                'columns' => [
                                                        'column1' => [
                                                                'value' => 'bar'
                                                        ],
                                                        'column2' => [
                                                                'field' => 'baz'
                                                        ]
                                                ]
                                        ]
                                ]
                        ]
                ]
        ];
    }

    /**
     * @param array $generalConfiguration
     * @param array $columnConfiguration
     * @test
     * @dataProvider validConfigurationProvider
     */
    public function isValidReturnsTrueForValidConfiguration(array $generalConfiguration, array $columnConfiguration): void
    {
        $configuration = $this->objectManager->get(Configuration::class);
        $configuration->setGeneralConfiguration($generalConfiguration);
        $configuration->setColumnConfiguration($columnConfiguration);
        self::assertTrue(
                $this->subject->isValid(
                        $configuration,
                        'col'
                )
        );
    }

    public function invalidConfigurationProvider(): array
    {
        return [
                'Data type "array": missing data-setting properties' => [
                        [
                                'data' => 'array'
                        ],
                        [],
                        AbstractMessage::ERROR
                ],
                'Data type "xml": missing data-setting properties' => [
                        [
                                'data' => 'xml'
                        ],
                        [],
                        AbstractMessage::NOTICE
                ],
                'Data type "array": conflicting data-setting properties' => [
                        [
                                'data' => 'array'
                        ],
                        [
                                'col' => [
                                        'field' => 'foo',
                                        'transformations' => [
                                                10 => [
                                                        'value' => 42
                                                ]
                                        ]
                                ]
                        ],
                        AbstractMessage::NOTICE
                ],
                'Data type "xml": conflicting data-setting properties' => [
                        [
                                'data' => 'xml'
                        ],
                        [
                                'col' => [
                                        'xpath' => 'item',
                                        'transformations' => [
                                                10 => [
                                                        'value' => 42
                                                ]
                                        ]
                                ]
                        ],
                        AbstractMessage::NOTICE
                ],
                'Children definition: no "table" property' => [
                        // No need for a general configuration
                        [],
                        [
                                'col' => [
                                        'children' => [
                                                'columns' => [
                                                        'column1' => [
                                                                'value' => 'bar'
                                                        ],
                                                        'column2' => [
                                                                'field' => 'baz'
                                                        ]
                                                ],
                                                'controlColumnsForUpdate' => 'column1',
                                                'controlColumnsForDelete' => 'column1'
                                        ]
                                ]
                        ],
                        AbstractMessage::ERROR
                ],
                'Children definition: no "columns" property' => [
                        // No need for a general configuration
                        [],
                        [
                                'col' => [
                                        'children' => [
                                                'table' => 'foo'
                                        ]
                                ]
                        ],
                        AbstractMessage::ERROR
                ],
                'Children definition: "columns" sub-property not an array' => [
                        // No need for a general configuration
                        [],
                        [
                                'col' => [
                                        'children' => [
                                                'table' => 'foo',
                                                'columns' => [
                                                        'column1' => 'bar'
                                                ],
                                                'controlColumnsForUpdate' => 'column1',
                                                'controlColumnsForDelete' => 'column1'
                                        ]
                                ]
                        ],
                        AbstractMessage::ERROR
                ],
                'Children definition: wrong "columns" sub-property' => [
                        // No need for a general configuration
                        [],
                        [
                                'col' => [
                                        'children' => [
                                                'table' => 'foo',
                                                'columns' => [
                                                        'column1' => [
                                                                'bar' => 'baz'
                                                        ]
                                                ],
                                                'controlColumnsForUpdate' => 'column1',
                                                'controlColumnsForDelete' => 'column1'
                                        ]
                                ]
                        ],
                        AbstractMessage::ERROR
                ],
                'Children definition: wrong "controlColumnsForUpdate" sub-property' => [
                        // No need for a general configuration
                        [],
                        [
                                'col' => [
                                        'children' => [
                                                'table' => 'foo',
                                                'columns' => [
                                                        'column1' => [
                                                                'bar' => 'baz'
                                                        ]
                                                ],
                                                'controlColumnsForUpdate' => 'columnX'
                                        ]
                                ]
                        ],
                        AbstractMessage::ERROR
                ],
                'Children definition: wrong "controlColumnsForDelete" sub-property' => [
                        // No need for a general configuration
                        [],
                        [
                                'col' => [
                                        'children' => [
                                                'table' => 'foo',
                                                'columns' => [
                                                        'column1' => [
                                                                'bar' => 'baz'
                                                        ]
                                                ],
                                                'controlColumnsForDelete' => 'columnX'
                                        ]
                                ]
                        ],
                        AbstractMessage::ERROR
                ]
        ];
    }

    /**
     * @param array $generalConfiguration
     * @param array $columnConfiguration
     * @param int $severity
     * @test
     * @dataProvider invalidConfigurationProvider
     */
    public function isValidRaisesMessageForInvalidConfiguration(array $generalConfiguration, array $columnConfiguration, int $severity): void
    {
        $configuration = $this->objectManager->get(Configuration::class);
        $configuration->setGeneralConfiguration($generalConfiguration);
        $configuration->setColumnConfiguration($columnConfiguration);
        $this->subject->isValid(
                $configuration,
                'col'
        );
        $results = $this->subject->getResults()->getForPropertyAndSeverity('field', $severity);
        self::assertGreaterThan(
                0,
                count($results)
        );
    }
}
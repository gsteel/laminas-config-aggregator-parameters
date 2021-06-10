<?php

declare(strict_types=1);

namespace LaminasTest\ConfigAggregatorParameters;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Laminas\ConfigAggregatorParameters\ParameterNotFoundException;
use Laminas\ConfigAggregatorParameters\ParameterPostProcessor;
use PHPUnit\Framework\TestCase;

class ParameterPostProcessorTest extends TestCase
{
    use ArraySubsetAsserts;

    public function parameterProvider(): array
    {
        return [
            'root-scoped-parameter' => [
                [
                    'foo' => 'bar',
                ],
                [
                    'config' => [
                        'param'          => '%foo%',
                        'used_parameter' => '%%foo%%',
                    ],
                ],
                [
                    'config' => [
                        'param'          => 'bar',
                        'used_parameter' => '%foo%',
                    ],
                ],
            ],
            'multi-level-parameter' => [
                [
                    'foo' => [
                        'bar' => 'baz',
                    ],
                ],
                [
                    'config' => [
                        'param'          => '%foo.bar%',
                        'used_parameter' => '%%foo.bar%%',
                    ],
                ],
                [
                    'config' => [
                        'param'          => 'baz',
                        'used_parameter' => '%foo.bar%',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider parameterProvider
     */
    public function testCanApplyParameters(array $parameters, array $configuration, array $expected)
    {
        $processor = new ParameterPostProcessor($parameters);
        $processed = $processor($configuration);

        $this->assertArraySubset($expected, $processed);
    }

    public function testCanDetectMissingParameter()
    {
        $processor = new ParameterPostProcessor([]);
        $this->expectException(ParameterNotFoundException::class);
        $processor(['foo' => '%foo%']);
    }

    public function testResolvesParameterizedParameters()
    {
        $processor = new ParameterPostProcessor([
            'foo'    => 'bar',
            'bar'    => 'baz',
            'baz'    => '%foo%',
            'nested' => [
                'foo' => '%bar%',
                'bar' => '%nested.foo%',
            ],
        ]);

        $processed = $processor(['foo' => '%nested.bar%']);

        $this->assertArraySubset([
            'foo'        => 'baz',
            'parameters' => [
                'nested' => [
                    'bar' => 'baz',
                ],
            ],
        ], $processed);
    }

    public function testResolvesParameterizedParametersInReversedOrder()
    {
        $processor = new ParameterPostProcessor([
            'foo' => '%bar%',
            'bar' => '%baz%',
            'baz' => 'qux',
        ]);

        $processed = $processor([]);

        $this->assertArraySubset([
            'parameters' => [
                'foo' => 'qux',
            ],
        ], $processed);
    }

    public function testProcessedConfigContainsParameterTypesafety(): void
    {
        $parameters = [
            'bool'   => true,
            'string' => 'lorem ipsum',
            'array'  => ['foo' => 'bar'],
            'int'    => 123,
            'float'  => 1.01,
        ];

        $processor = new ParameterPostProcessor($parameters);
        $processed = $processor([
            'bool'   => '%bool%',
            'string' => '%string%',
            'array'  => '%array%',
            'int'    => '%int%',
            'float'  => '%float%',
        ]);

        unset($processed['parameters']);
        self::assertEquals([
            'bool'   => true,
            'string' => 'lorem ipsum',
            'array'  => ['foo' => 'bar'],
            'int'    => 123,
            'float'  => 1.01,
        ], $processed);
    }

    public function testResolvedParametersApplyTypesafety(): void
    {
        $parameters = [
            'bool'   => true,
            'string' => 'lorem ipsum',
            'array'  => ['foo' => 'bar'],
            'int'    => 123,
            'float'  => 1.01,
        ];

        $processed = (new ParameterPostProcessor(
            $parameters + [
                'nested' => [
                    'bool'   => '%bool%',
                    'string' => '%string%',
                    'array'  => '%array%',
                    'int'    => '%int%',
                    'float'  => '%float%',
                ],
            ]
        ))([]);

        $processedParameters       = $processed['parameters'];
        $processedNestedParameters = $processedParameters['nested'];
        unset($processedParameters['nested']);

        foreach ($processedNestedParameters as $parameter => $parameterValue) {
            $originalValue = $parameters[$parameter];
            self::assertEquals($originalValue, $parameterValue);
        }
    }
}

<?php

class ValueResolverTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var \StackFormation\ValueResolver
     */
    protected $valueResolver;

    public function setUp() {
        $this->valueResolver = $this->getMockedPlaceholderResolver();
        parent::setUp();
    }

    public function getMockedPlaceholderResolver() {
        $config = $this->getMock('\StackFormation\Config', [], [], '', false);
        $config->method('getGlobalVars')->willReturn([
            'GlobalFoo' => 'GlobalBar',
            'GlobalFoo2' => 'GlobalBar2',
            'GlobalBar' => 'GlobalFoo3',
            'rescursiveA' => '{var:rescursiveB}',
            'rescursiveB' => 'Hello',
            'circularA' => '{var:circularB}',
            'circularB' => '{var:circularA}',
            'directCircular' => '{var:directCircular}',
        ]);

        $stackFactoryMock = $this->getMock('\StackFormation\StackFactory', [], [], '', false);
        $stackFactoryMock->method('getStackOutput')->willReturn('dummyOutput');
        $stackFactoryMock->method('getStackResource')->willReturn('dummyResource');
        $stackFactoryMock->method('getStackParameter')->willReturn('dummyParameter');

        $profileManagerMock = $this->getMock('\StackFormation\Profile\Manager', [], [], '', false);

        $placeholderResolver = new \StackFormation\ValueResolver(
            null,
            $profileManagerMock,
            $config
        );
        return $placeholderResolver;
    }

    /**
     * @test
     */
    public function defaultIsTrue()
    {
        $this->assertTrue($this->valueResolver->isTrue('default'));
    }

    /**
     * @test
     * @dataProvider isConditionDataProvider
     */
    public function checkKey($key, $expectedValue, $putenv=null) {

        $blueprint = $this->getMock('\StackFormation\Blueprint', [], [], '', false);
        $blueprint->method('getVars')->willReturn(['BlueprintFoo' => 'BlueprintBar']);
        if ($putenv) {
            putenv($putenv);
        }
        $actualValue = $this->valueResolver->isTrue($key, $blueprint);
        $this->assertEquals($expectedValue, $actualValue);
    }

    public function isConditionDataProvider()
    {
        $values = [
            ['1==1', true],
            ['1 ==1', true],
            ['1== 1', true],
            [' 1== 1', true],
            ['==', true],
            ['0==1', false],
            ['a==b', false],
            ['a==', false],
            ['{var:GlobalFoo}==GlobalBar', true],
            ['{var:GlobalFoo}=={var:GlobalFoo}', true],
            ['{env:FOO}==42', true, 'FOO=42'],
            ['{env:VARWITHOUTVALUE:42}==42', true],
            ['{env:VARWITHOUTVALUE:42}==41', false],
            ['42=={env:FOO}', true, 'FOO=42'],
            ['{env:FOO}==43', false, 'FOO=42'],
            ['43=={env:FOO}', false, 'FOO=42'],
            ['{env:FOO}=={var:GlobalFoo}', true, 'FOO=GlobalBar'],
            ['GlobalBar=={var:{env:FOO}}', true, 'FOO=GlobalFoo'],
            ['{var:BlueprintFoo}==BlueprintBar', true],
            ['prod~=/^prod$/', true],
            ['prod~=/^(prod|qa)$/', true],
            ['prd~=/^(prod|qa)$/', false],
            ['test1~=/^test.$/', true],
        ];
        $invertedValues = [];
        foreach ($values as $value) {
            if (strpos($value[0], '==') !== false) {
                $value[0] = str_replace('==', '!=', $value[0]);
                $value[1] = !$value[1];
                $invertedValues[] = $value;
            }
        }
        return array_merge($values, $invertedValues);
    }

    /**
     * @test
     * @dataProvider invalidConditionProvider
     */
    public function invalidCondition($key) {
        $this->setExpectedException('Exception', 'Invalid condition');
        $this->valueResolver->isTrue($key);
    }

    public function invalidConditionProvider() {
        return [
            ['foo'],
            ['foo=bar'],
        ];
    }

    /**
     * @test
     * @param array $conditions
     * @param $expectedValue
     * @dataProvider resolveDataProvider
     */
    public function resolve(array $conditions, $expectedValue, $putenv=null)
    {
        if ($putenv) {
            putenv($putenv);
        }
        $blueprint = $this->getMock('\StackFormation\Blueprint', [], [], '', false);
        $blueprint->method('getVars')->willReturn(['BlueprintFoo' => 'BlueprintBar']);
        $actualValue = $this->valueResolver->resolveConditionalValue($conditions, $blueprint);
        $this->assertEquals($expectedValue, $actualValue);
    }

    public function resolveDataProvider() {
        return [
            [['default' => 42], 42],
            [['default' => '{env:FOO}'], '{env:FOO}', 'FOO=lala'],
            [['default' => '{var:GlobalFoo}'], '{var:GlobalFoo}'],
            [['default' => '{env:FOO}{var:GlobalFoo}'], '{env:FOO}{var:GlobalFoo}', 'FOO=lala'],
            [['1==0' => 41, 'default' => 42], 42],
            [['1==0' => 41, 'default' => '{env:FOO}'], '{env:FOO}', 'FOO=lala'],
            [['1==0' => 41, 'default' => '{var:GlobalFoo}'], '{var:GlobalFoo}'],
            [['1==0' => '{env:FOO}{var:GlobalFoo}'], '', 'FOO=lala'],
            [['1==1' => '{env:FOO}'], '{env:FOO}', 'FOO=lala'],
            [['1==1' => '{var:GlobalFoo}'], '{var:GlobalFoo}'],
            [['1==1' => '{env:FOO}{var:GlobalFoo}'], '{env:FOO}{var:GlobalFoo}', 'FOO=lala'],
            [['default' => 42, '1==0' => 41], 42],
            [['default' => 42, '1==1' => 41], 42],
            [['1==2' => 42, '1==0' => 41], ''], // nothing matched
            [['{env:FOO}==prod' => 41, 'default' => 42], 41, 'FOO=prod'],
            [['{env:FOO}==prod' => 41, 'default' => 42], 42, 'FOO=stage'],
            [['{env:FOO}==prod' => 41, '{env:FOO}==stage' => 40, 'default' => 42], 40, 'FOO=stage'],
            [['{env:FOO}=={var:GlobalFoo}' => 41, '{env:FOO}==stage' => 40, 'default' => 42], 41, 'FOO=GlobalBar'],
            [['{env:FOO}=={var:BlueprintFoo}' => 41, '{env:FOO}==stage' => 40, 'default' => 42], 41, 'FOO=BlueprintBar'],
        ];
    }

    /**
     * @test
     */
    public function missingEnv()
    {
        $this->setExpectedException('Exception', "Environment variable 'DDD' not found");
        $this->valueResolver->resolveConditionalValue(['{env:DDD}' => 13]);
    }

    /**
     * @test
     */
    public function missingVar()
    {
        $this->setExpectedException('Exception', "Variable 'DDD' not found (Type:conditional_value, Key:{var:DDD})");
        $this->valueResolver->resolveConditionalValue(['{var:DDD}' => 13]);
    }

    /**
     * @test
     */
    public function nestedVars()
    {
        $result = $this->valueResolver->resolvePlaceholders('{var:{var:GlobalFoo}}');
        $this->assertEquals('GlobalFoo3', $result);
    }

    /**
     * @test
     */
    public function recursiveReferences()
    {
        $result = $this->valueResolver->resolvePlaceholders('{var:rescursiveA}');
        $this->assertEquals('Hello', $result);
    }

    /**
     * @test
     */
    public function directCircularReferences()
    {
        $this->setExpectedException('Exception', 'Direct circular reference detected');
        $result = $this->valueResolver->resolvePlaceholders('{var:directCircular}');
    }

    /**
     * @test
     */
    public function circularReferences()
    {
        $this->setExpectedException('Exception', 'Max nesting level reached. Looks like a circular dependency.');
        $this->valueResolver->resolvePlaceholders('{var:circularA}');
    }

}
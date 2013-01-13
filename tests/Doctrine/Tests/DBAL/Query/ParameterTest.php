<?php

namespace Doctrine\Tests\DBAL\Query;

use Doctrine\DBAL\Query\Parameter;
use Doctrine\DBAL\Types\Type;
use PDO;

/**
 * @group parameters
 */
class ParameterTest extends \Doctrine\Tests\DbalTestCase
{
    static public function dataExpandParameters()
    {
        return array(
            array(
                new Parameter('foo',  PDO::PARAM_STR),
                'foo',
                PDO::PARAM_STR,
                '?'
            ),
            array(
                new Parameter(1,  PDO::PARAM_INT),
                1,
                PDO::PARAM_INT,
                '?'
            ),
            array(
                new Parameter(1,  PDO::PARAM_INT),
                1,
                PDO::PARAM_INT,
                '?'
            ),
            array(
                new Parameter(array(1,2),  PDO::PARAM_INT),
                array(1,2),
                PDO::PARAM_INT,
                '?, ?'
            ),
            array(
                new Parameter(array('foo', 'bar'),  PDO::PARAM_STR),
                array('foo', 'bar'),
                PDO::PARAM_STR,
                '?, ?'
            ),
            array(
                new Parameter(new \DateTime('2012-12-12'), Type::DATE),
                new \DateTime('2012-12-12'),
                Type::DATE,
                '?'
            ),
            array(
                new Parameter(array(new \DateTime('2011-11-11'), new \DateTime('2012-12-12')), Type::DATE),
                array(new \DateTime('2011-11-11'), new \DateTime('2012-12-12')),
                Type::DATE,
                '?, ?'
            ),
        );
    }

    /**
     * @dataProvider dataExpandParameters
     * @param Parameter $parameter
     * @param mixed     $type
     * @param mixed     $placeholder
     */
    public function testParameter(Parameter $parameter, $value, $type, $placeholder)
    {
        $this->assertEquals($parameter->getValue(), $value, "Values dont match.");
        $this->assertEquals($parameter->getType(), $type, "Params dont match.");
        $this->assertEquals($parameter->getBindingPlaceholder(), $placeholder, "Placeholder dont match.");
    }
}

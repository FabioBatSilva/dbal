<?php

namespace Doctrine\Tests\DBAL\Query;

use Doctrine\Tests\DBAL\SQLParserUtilsTest;
use Doctrine\DBAL\Query\Parameter;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Sql;
use \PDO;

/**
 * @group parameters
 */
class SqlTest extends \Doctrine\Tests\DbalTestCase
{
    static public function dataExpandParameters()
    {
        $values = array(
            //Positional Parameters
            array(
                new Sql('SELECT * FROM table WHERE value = ?', array(
                    new Parameter('foo', PDO::PARAM_STR)
                )),
                'SELECT * FROM table WHERE value = ?',
                array('foo'),
                array_fill(0, 1, PDO::PARAM_STR)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (?)', array(
                    new Parameter(array(1, 2, 3), PDO::PARAM_INT)
                )),
                'SELECT * FROM table WHERE value IN (?, ?, ?)',
                array(1, 2, 3),
                array_fill(0, 3, PDO::PARAM_INT)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (?)', array(
                    new Parameter(array('foo', 'bar'), PDO::PARAM_STR)
                )),
                'SELECT * FROM table WHERE value IN (?, ?)',
                array('foo', 'bar'),
                array_fill(0, 2, PDO::PARAM_STR)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (?, ?)', array(
                    new Parameter(array(1, 2), PDO::PARAM_INT),
                    new Parameter(array(3, 4), PDO::PARAM_INT)
                )),
                'SELECT * FROM table WHERE value IN (?, ?, ?, ?)',
                array(1, 2, 3, 4),
                array_fill(0, 4, PDO::PARAM_INT)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (?, ?)', array(
                    new Parameter(new \DateTime('2011-11-11'), Type::DATE),
                    new Parameter(new \DateTime('2012-12-12'), Type::DATE)
                )),
                'SELECT * FROM table WHERE value IN (?, ?)',
                array(new \DateTime('2011-11-11'), new \DateTime('2012-12-12')),
                array_fill(0, 2, Type::DATE)
            ),
            // Named parameters
            array(
                new Sql('SELECT * FROM table WHERE value = :value', array(
                    'value' => new Parameter(1, PDO::PARAM_INT)
                )),
                'SELECT * FROM table WHERE value = ?',
                array(1),
                array_fill(0, 1, PDO::PARAM_INT),
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (:value)', array(
                    'value' => new Parameter(array(1, 2, 3), PDO::PARAM_INT)
                )),
                'SELECT * FROM table WHERE value IN (?, ?, ?)',
                array(1, 2, 3),
                array_fill(0, 3, PDO::PARAM_INT)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (:value1, :value2)', array(
                    'value1' => new Parameter(array(1, 2), PDO::PARAM_INT),
                    'value2' => new Parameter(array(3, 4), PDO::PARAM_INT)
                )),
                'SELECT * FROM table WHERE value IN (?, ?, ?, ?)',
                array(1, 2, 3, 4),
                array_fill(0, 4, PDO::PARAM_INT)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (:value1, :value2)', array(
                    'value1' => new Parameter(1, PDO::PARAM_INT),
                    'value2' => new Parameter(2, PDO::PARAM_INT)
                )),
                'SELECT * FROM table WHERE value IN (?, ?)',
                array(1, 2,),
                array_fill(0, 2, PDO::PARAM_INT)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (:value1, :value2)', array(
                    'value1' => new Parameter(array(new \DateTime('2009-09-09'), new \DateTime('2010-10-10')), Type::DATE),
                    'value2' => new Parameter(array(new \DateTime('2011-11-11'), new \DateTime('2012-12-12')), Type::DATE),
                )),
                'SELECT * FROM table WHERE value IN (?, ?, ?, ?)',
                array(new \DateTime('2009-09-09'), new \DateTime('2010-10-10'), new \DateTime('2011-11-11'), new \DateTime('2012-12-12')),
                array_fill(0, 4, Type::DATE)
            ),
            array(
                new Sql('SELECT * FROM table WHERE value IN (:value)', array(
                    'value1' => new Parameter(array(new \DateTime('2009-09-09')), Type::DATE),
                    'value2' => new Parameter(array(new \DateTime('2011-11-11')), Type::DATE),
                )),
                'SELECT * FROM table WHERE value IN (?)',
                array(),
                array(),
            ),
        );
        
        // test backward compatibility - SQLParserUtils
        foreach (SQLParserUtilsTest::dataExpandListParameters() as $value) {
            list($query, $params, $types, $expectedQuery, $expectedParams, $expectedTypes) = $value;

            $parameters = Parameter::createParameters($params, $types);

            $values[] = array(new Sql($query, $parameters), $expectedQuery, $expectedParams, $expectedTypes);
        }

        return $values;
    }

    /**
     * @dataProvider dataExpandParameters
     * 
     * @param Sql       $sql
     * @param string    $query
     * @param array     $params
     * @param array     $types
     */
    public function testSql(Sql $sql, $query, array $params, array $types)
    {

        $this->assertEquals($query, (string)$sql);
        $this->assertEquals($query, $sql->getBindingQuery());

        $parameters = $sql->getBindingParameters();

        foreach ($params as $key => $value) {
            $this->assertArrayHasKey($key, $parameters);

            $type  = $types[$key];
            $param = $parameters[$key];

            $this->assertEquals($value, $param->getValue());
            $this->assertEquals($type, $param->getType());
        }
    }
}

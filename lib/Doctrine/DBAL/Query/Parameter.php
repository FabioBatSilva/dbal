<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Defines a Query Parameter.
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 * @since  2.4
 */
class Parameter
{
    /**
     * @var mixed The parameter value.
     */
    private $value;

    /**
     * @var mixed The parameter type.
     */
    private $type;

    /**
     * Constructor.
     *
     * @param mixed  $value Parameter value
     * @param mixed  $type  Parameter type
     */
    public function __construct($value, $type = null)
    {
        $this->setValue($value, $type);
    }

    /**
     * Gets the Parameter value.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Gets the Parameter type.
     *
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return boolean
     */
    public function isArray()
    {
        return is_array($this->value);
    }

    /**
     * Sets the Parameter value.
     *
     * @param mixed $value Parameter value.
     * @param mixed $type  Parameter type.
     */
    public function setValue($value, $type = null)
    {
        if ($type === null) {
            $type = \PDO::PARAM_STR;
        }

        $this->value = $value;
        $this->type  = $type;
    }

    /**
     * Gets the binding type of a given type. The given type can be a PDO or DBAL mapping type.
     *
     * @return integer The binding type
     */
    public function getBindingType()
    {
        if (is_string($this->type)) {
            return Type::getType($this->type)->getBindingType();
        }

        return $this->type;
    }

    /**
     * Gets the parameter value.
     *
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     * @return mixed
     */
    public function getBindingValue(AbstractPlatform $platform)
    {
        if (is_string($this->type)) {
            return Type::getType($this->type)->convertToDatabaseValue($this->value, $platform);
        }

        return $this->value;
    }

    /**
     * Gets the Parameter placeholder.
     *
     * @return mixed
     */
    public function getBindingPlaceholder()
    {
        if ($this->isArray() && ! empty($this->value)) {
            return implode(', ', array_fill(0, count($this->value), '?'));
        }

        return '?';
    }

    /**
     * @param array $params
     * @param array $types
     *
     * @return array<\Doctrine\DBAL\Query\Parameter>
     */
    public static function createParameters(array $params, array $types = array())
    {
        $parameters = array();

        foreach ($params as $key => $value) {

            if ($value instanceof self) {
                $parameters[$key] = $value;

                continue;;
            }

            $type = null;

            if(isset($types[$key])) {
                $type = $types[$key];
            }

            if ($type === Connection::PARAM_INT_ARRAY || $type === Connection::PARAM_STR_ARRAY) {
                $type = $type - Connection::ARRAY_PARAM_OFFSET;
            }

            $parameters[$key] = new self($value, $type);
        }

        return $parameters;
    }
}
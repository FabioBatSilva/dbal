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

use Doctrine\DBAL\SQLParserUtils;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Defines a SQL Command.
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 * @since  2.4
 */
class Sql
{
    /**
     * @var string The parsed query string.
     */
    private $bindingQuery;

    /**
     * @var array The query parameters.
     */
    private $bindingParameters;

    /**
     * @var string The original query string.
     */
    private $query;

    /**
     * @var array The original query parameters.
     */
    private $parameters = array();

    /**
     * Constructor.
     *
     * @param string                                $query      The SQL query to execute.
     * @param array<\Doctrine\DBAL\Query\Parameter> $parameters The parameters to bind to the query.
     */
    public function __construct($query, array $parameters = array())
    {
        $this->query = $query;

        if ( ! empty($parameters)) {
            $this->setParameters($parameters);
        }
    }

    /**
     * @return boolean TRUE if the query is parametrized.
     */
    public function isParametrized()
    {
        return ( ! empty($this->parameters));
    }

    /**
     * @return boolean TRUE if the the parameters are positional.
     */
    public function isPositional()
    {
        return is_int(key($this->parameters));
    }

    /**
     * Binds the parameter to a given statement.
     * 
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     * @param \Doctrine\DBAL\Driver\Statement $stmt
     *
     * return \Doctrine\DBAL\Platforms\AbstractPlatform;
     */
    public function bindStatement(AbstractPlatform $platform, Statement $stmt)
    {
        foreach ($this->getBindingParameters() as $key => $parameter) {
            $bindIndex    = $key + 1;
            $bindingType  = $parameter->getBindingType();
            $bindingValue = $parameter->getBindingValue($platform);

            $stmt->bindValue($bindIndex, $bindingValue, $bindingType);
        }

        return $stmt;
    }

    /**
     * @return array
     */
    public function getBindingTypes()
    {
        $types        = array();
        $parameters   = $this->getBindingParameters();

        foreach ($parameters as $key => $parameter) {
            $types[$key] = $parameter->getBindingType();
        }

        return $types;
    }

    /**
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     * @return array
     */
    public function getBindingValues(AbstractPlatform $platform)
    {
        $values       = array();
        $parameters   = $this->getBindingParameters();

        foreach ($parameters as $key => $parameter) {
            $values[$key] = $parameter->getBindingValue($platform);
        }

        return $values;
    }

    /**
     * Gets the query string.
     *
     * @return string
     */
    public function getBindingQuery()
    {
        if ($this->bindingQuery !== null) {
            return $this->bindingQuery;
        }

        $this->parse();

        return $this->bindingQuery;
    }

    /**
     * Gets the original SQL value.
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Gets the Parameters.
     *
     * @return array
     */
    public function getBindingParameters()
    {
        if ($this->bindingParameters !== null) {
            return $this->bindingParameters;
        }

        $this->parse();

        return $this->bindingParameters;
    }

    /**
     * Set parameters.
     *
     * @param array<\Doctrine\DBAL\Query\Parameter> $parameters The parameters to bind to the query.
     */
    public function setParameters(array $parameters)
    {
        $this->bindingQuery                = null;
        $this->parameters   = $parameters;
    }

    /**
     * @return string Query string.
     */
    public function __toString()
    {
        return $this->bindingQuery ?: $this->getBindingQuery();
    }

    /**
     * Can rewrite the sql statement with regard to named and array parameters.
     */
    private function parse()
    {
        $bindIndex      = -1;
        $positions      = array();
        $isPositional   = $this->isPositional();
        $placeholders   = SQLParserUtils::getPlaceholderPositions($this->query, $isPositional);

        foreach ($this->parameters as $name => $param) {
            ++$bindIndex;

            if ( ! $param instanceof Parameter) {
                throw new \InvalidArgumentException(sprintf(
                    'An instance of "Parameter\Doctrine\DBAL\Query" expected, "%s" given.',
                    is_object($param) ? get_class($param) : gettype($param)
                ));
            }

            if ( ! $param->isArray()) {
                continue;
            }

            if ($isPositional) {
                $name = $bindIndex;
            }

            $positions[$name] = true;
        }

        $isPositional
            ? $this->parsePositionalParameters($placeholders, $positions)
            : $this->parseNamedParameters($placeholders, $positions);
    }

    /**
     * Parse a query with positional parameters
     * 
     * @param array $placeholders
     * @param array $positions
     */
    private function parsePositionalParameters(array $placeholders, array $positions)
    {
        $paramOffset = 0;
        $queryOffset = 0;
        $query       = $this->query;
        $parameters  = $this->parameters;

        foreach ($placeholders as $needle => $position) {
            if ( ! isset($positions[$needle])) {
                continue;
            }

            //Increase positions.
            $needle      += $paramOffset;
            $position    += $queryOffset;

            //Handle the current parameter.
            $param        = $parameters[$needle];
            $paramValue   = $param->getValue();
            $count        = count($paramValue);

            //Expand the placeholde.
            $placeholder  = $param->getBindingPlaceholder();
            $query        = substr($query, 0, $position) . $placeholder . substr($query, $position + 1);

            // Expand larger by number of parameters minus the replaced needle.
            $paramOffset += ($count - 1);
            $queryOffset += (strlen($placeholder) - 1);

            //Expand the array parameter
            $expandedList = array_map(function($value) use ($param)
            {
                return new Parameter($value, $param->getType());
            }, $paramValue);

            //Merge the parameter list
            $parameters = array_merge(
                array_slice($parameters, 0, $needle),
                $expandedList,
                array_slice($parameters, $needle + 1)
            );
        }

        $this->bindingQuery        = $query;
        $this->bindingParameters   = array_values($parameters);
    }

    /**
     * Parse a query with named parameters
     *
     * @param array $placeholders
     * @param array $positions
     */
    private function parseNamedParameters($placeholders, $positions)
    {
        $queryOffset = 0;
        $params      = array();
        $query       = $this->query;
        $parameters  = $this->parameters;

        foreach ($placeholders as $position => $paramName) {

            $paramLen = strlen($paramName) + 1;

            //Placeholder parameter not found
            if ( ! isset($parameters[$paramName])) {
                $position    += $queryOffset;
                $queryOffset -= ($paramLen - 1);
                $query        = substr($query, 0, $position) . '?' . substr($query, ($position + $paramLen));

                continue;
            }

            //Handle the current parameter
            $param       = $parameters[$paramName];
            $placeholder = $param->getBindingPlaceholder();

            //Replace :namedParameter to ?
            if ( ! isset($positions[$paramName])) {
                $position    += $queryOffset;
                $queryOffset -= ($paramLen - 1);

                // join query and parameters
                $params[]   = $param;
                $query      = substr($query, 0, $position) . $placeholder . substr($query, ($position + $paramLen));

                continue;
            }

            //Expand the array parameter
            $paramValue   = $param->getValue();
            $expandedList = array_map(function($value) use ($param)
            {
                return new Parameter($value, $param->getType());
            }, $paramValue);

            //Increase positions.
            $position    += $queryOffset;
            $queryOffset += (strlen($placeholder) - $paramLen);

            // Expand query and merge parameters.
            $query  = substr($query, 0, $position) . $placeholder . substr($query, ($position + $paramLen));
            $params = array_merge($params, $expandedList);
        }

        $this->bindingQuery        = $query;
        $this->bindingParameters   = $params;
    }
}
<?php

namespace Amadiify\DB;

class FetchRow implements \ArrayAccess
{
    private $__;
    private $dd;

    public function __construct(&$object, $fetch)
    {
        $this->dd = is_object($fetch) ? toArray($fetch) : $fetch;
        $this->__ = $object;
    }

    public function __get($name)
    {
        if (isset($this->dd[$name]))
        {
            return $this->dd[$name];
        }

        return $this->__->{$name};
    }

    public function __call($method, $data)
    {
        $this->__->getPacked = $this->dd;

        $data = call_user_func_array([$this->__, $method], $data);

        return $data;
    }

    // arrayaccess methods
    public function offsetExists($offset)
    {
        if (isset($this->dd[$offset]))
        {
            return true;
        }

        return false;
    }

    public function offsetGet($name)
    {
        if ($this->offsetExists($name))
        {
            return $this->dd[$name];
        }

        return null;
    }

    public function offsetSet($key, $val)
    {
        $this->dd[$key] = $val;
    }

    public function offsetUnset($name)
    {
        if ($this->offsetExists($name))
        {
            unset($this->dd[$name]);
        }
    }
}
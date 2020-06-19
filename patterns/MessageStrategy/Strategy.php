<?php

namespace Patterns\MessageStrategy;


use Patterns\MessageStrategy\Interfaces\BaseStrategyInterface;
use Patterns\MessageStrategy\Interfaces\MessageStrategyInterface;

class Strategy implements BaseStrategyInterface
{
    protected int $count = 20;
    protected int $page = 1;
    private $strategy;

    public function executeStrategy(string $function, $data = [])
    {
        if ($data) {
            return call_user_func([$this->strategy,$function],$data);
        }
        return call_user_func([$this->strategy,$function]);
    }

    /**
     * @return mixed
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * @param mixed $strategy
     */
    public function setStrategy($strategy): void
    {
        $this->strategy = $strategy;
    }
}
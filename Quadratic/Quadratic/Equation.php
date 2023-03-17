<?php

namespace Quadratic;

class Equation
{
    /**
     * @var int $a
     */
    private int $a;

    /**
     * @var int $b
     */
    private int $b;

    /**
     * @var int $c
     */
    private int $c;

    public function __construct(IArguments $arguments)
    {
        $this->a = $arguments->getArgument('a');
        $this->b = $arguments->getArgument('b');
        $this->c = $arguments->getArgument('c');
    }

    /**
     * Start quadratic equation
     * @return string|bool
     */
    public function start(): string|bool
    {
        $discriminant = $this->getDiscriminant();
        if ($discriminant < 0) {
            return 'У уравнения нет действительных корней!';
        } elseif ($discriminant == 0) {
            return 'У уравнения один корен: ' . (-$this->b / ($this->a * 2));
        } else {
            $divison = $this->a * 2;
            $x1 = (-$this->b + $discriminant) / $divison;
            $x2 = (-$this->b - $discriminant) / $divison;
            return "У уравнения два корня: {$x1}, {$x2}";
        }
    }

    /**
     * Get Discriminant
     * @return int
     */
    private function getDiscriminant(): int
    {
        return pow($this->b, 2) - (4 * $this->a * $this->c);
    }

}
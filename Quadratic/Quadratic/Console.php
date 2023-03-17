<?php

namespace Quadratic;

class Console {

    /**
     * @var string|bool $result
     */
    private string|bool $result;

    /**
     * @var Arguments $arguments
     */
    private Arguments $arguments;

    /**
     * @var Equation $equation
     */
    private Equation $equation;

    public function __construct()
	{
        $this->arguments = new Arguments();
        $this->equation = new Equation($this->arguments);
        $this->result = $this->equation->start();
        $this->output();
	}

    /**
     * Output result if exists
     * @return void
     */
    protected function output(): void
    {
        if (!$this->result) {
            print "\033[31mНет результата!";
        } else {
            if (!is_bool($this->result)) {
                print "\033[32m{$this->result}";
            }
        }
    }
}
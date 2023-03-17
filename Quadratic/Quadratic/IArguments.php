<?php

namespace Quadratic;

interface IArguments
{
    /**
     * @var const DEFAULT_VALUES
     */
    public const DEFAULT_VALUES = [];

    /**
     * Get argument
     * @param string $argument
     * @return mixed
     */
    public function getArgument(string $argument);
}
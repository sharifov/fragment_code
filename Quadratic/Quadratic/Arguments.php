<?php

namespace Quadratic;

use Exception;

class Arguments implements IArguments
{
    /**
     * @var const DEFAULT_VALUES
     */
    public const DEFAULT_VALUES = [
        'a' => 4,
        'b' => -3,
        'c' => 1
    ];

    /**
     * @var array|false|false[]|string[]
     */
    private array $options;

    public function __construct()
    {
        $this->options = getopt('a:b:c:');
        $this->castArguments();
    }

    /**
     * Get argument
     * @param string $argument
     * @return string
     * @throws Exception
     */
    public function getArgument(string $argument): string
    {
        return (!empty($this->options[$argument]) ? $this->options[$argument] : self::DEFAULT_VALUES[$argument]);
    }

    /**
     * Cast arguments to integers
     * @return void
     * @throws Exception
     */
    protected function castArguments(): void
    {
        foreach ($this->options as &$option) {
            $option = intval($option);
            if (!$option) {
                throw new Exception('Argument need be integer!');
            }
        }
    }
}
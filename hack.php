<?php

class ServerFinder
{
    public array $data;
    public const DATA_FOLDER = "./data/";
    public const OUTPUT_FOLDER = "./out/";

    public function __construct()
    {
        $this->cleanUp();

        // Collect data
        $this->data = [];
        foreach (glob(ServerFinder::DATA_FOLDER . "*.json") as $data)
        {
            array_push($this->data, ...json_decode(file_get_contents($data)));
        }

        echo "\e[1;32m" . count($this->data) . "\e[0m Servers found" . PHP_EOL;
    }

    public function run(): void
    {
        foreach ($this->data as $server)
        {
            if ($this->checker($server))
            {
                $this->convertFavicon($server);
            }
        }
    }

    public function cleanUp(): void
    {
        if (is_dir(ServerFinder::OUTPUT_FOLDER))
        {
            exec('rm -rf ' . ServerFinder::OUTPUT_FOLDER); // quick and lazy, only works on Linux and macOS
        }

        mkdir(ServerFinder::OUTPUT_FOLDER);
    }

    public function checker($server): bool
    {
        if (isset($server->pong->favicon) &&
            isset($server->pong->players->max) &&
            $server->pong->players->max === 35)
        {
            return true;
        }

        return false;
    }

    public function convertFavicon($server): void
    {
        echo "Hit: \e[0;34m" . $server->ip . "\e[0m" . PHP_EOL;

        $imageInfo = explode(";base64,", $server->pong->favicon);
        $image = str_replace(' ', '+', $imageInfo[1]);

        file_put_contents(ServerFinder::OUTPUT_FOLDER . $server->ip . ".png", base64_decode($image));
    }
}

$app = new ServerFinder;
$app->run();
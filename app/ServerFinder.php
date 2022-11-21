<?php

namespace App;

use xPaw\MinecraftQuery;
use xPaw\MinecraftQueryException;

// Catch Signals
pcntl_async_signals(true); // PHP 7.1+ function
pcntl_signal(SIGINT, function () {
    exit(0);
});
pcntl_signal(SIGTERM, function () {
    exit(0);
});

class ServerFinder
{
    public array $data;
    public const DATA_FOLDER = "./data2/";
    public const OUTPUT_FOLDER = "./out/";
    public array $outputData;

    private int $mainPID;

    public function __construct()
    {
        $this->mainPID = getmypid();

        // Collect data
        $this->data = [];
        foreach (glob(ServerFinder::DATA_FOLDER . "*.json") as $data)
        {
            array_push($this->data, ...json_decode(file_get_contents($data)));
        }

        echo "\e[1;32m" . count($this->data) . "\e[0m IP found" . PHP_EOL;
    }

    public function run(): void
    {
        $this->cleanUp();

        $chunkServers = array_chunk($this->data, 20); // Adjust length to 50-100 on 4-6 CPUs
        $chunks = count($chunkServers);

        echo "\e[1;33m" . $chunks . "\e[0m Servers chunks" . PHP_EOL;

        foreach ($chunkServers as $childServers)
        {
            $pid = pcntl_fork();

            if ($pid == -1)
            {
                exit("\e[0;31mError forking..\e[0m\n");
            } else if ($pid == 0)
            {
                echo "\n********** CHILD Started with PID id: [ \e[0;31m" . getmypid() . "\e[0m ] | Jobs to process: [ \e[0;32m" . count($childServers) . "\e[0m ] **********\n";
                $this->fork($childServers, getmypid());
                exit(1); // If Child not exits through excChild function
            }
        }

        // Main process is waiting until all child's finishing
        while (pcntl_waitpid(0, $status) != -1)
        {
            $status = pcntl_wexitstatus($status);
        }

        $this->saveFinalList();

//        foreach ($this->data as $server)
//        {
//            if ($this->checker($server))
//            {
//                $this->convertFavicon($server);
//            }
//        }
    }


    public function fork($servers, $pid): void
    {
        $data = [];
        foreach ($servers as $server)
        {
            echo PHP_EOL . "Run {$server->ip}" . PHP_EOL;
            $Query = new MinecraftQuery();

            try
            {
                $Query->Connect($server->ip, $server->ports[0]->port, 3);
                $info = $Query->GetInfo();
                print_r($info);
                $data[] = $info;
//                print_r($Query->GetPlayers());
            } catch (MinecraftQueryException $e)
            {
                echo $e->getMessage();
            }
        }

        if (!empty($data)) $this->saveOutput($data, $pid);
    }

    public function saveOutput($data, $pid): void
    {
        file_put_contents(ServerFinder::OUTPUT_FOLDER . "{$pid}-out.json", json_encode($data));
    }

    public function saveFinalList(): void
    {
        // Collect data
        $this->outputData = [];
        foreach (glob(ServerFinder::OUTPUT_FOLDER . "*.json") as $data)
        {
            array_push($this->outputData, ...json_decode(file_get_contents($data)));
        }

        $this->cleanUp();

        file_put_contents(ServerFinder::OUTPUT_FOLDER . "result.json", json_encode($this->outputData));

        echo  PHP_EOL . "\e[1;32m" . count($this->data) . "\e[0m IPs scanned" . PHP_EOL;
        echo  PHP_EOL . "\e[1;32m" . count($this->outputData) . "\e[0m Servers found" . PHP_EOL;
        echo  "\e[0;34mCheck out/final.json \e[0m" . PHP_EOL;
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
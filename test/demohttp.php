<?php


for ($i = 0; $i < 10000; $i++) {
    $time = microtime(true);

    $guid = md5(mt_rand(1000000, 9999999) . mt_rand(1000000, 9999999) . microtime(true));
    //mutil call sync wait result
    $data = array(
        "guid" => $guid,

        "api" => array(
            "oak" => array("name" => "/module_d/oakdf", "param" => array("dsaf" => "32111321")),
            "cd" => array("name" => "/module_e/oakdfff", "param" => array("codo" => "f11ds")),
        )
    ,
    );

    $data_string = "params=" . urlencode(json_encode($data)) . "&guid=" . $guid;

    $ch = curl_init('http://127.0.0.1:9566/api/multisync');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Connection: Keep-Alive',
            'Keep-Alive: 300',
        )
    );

    $result = curl_exec($ch);
    var_dump(json_decode($result, true));


    //multi call no wait result
    $guid = md5(mt_rand(1000000, 9999999) . mt_rand(1000000, 9999999) . microtime(true));
    $data = array(
        "guid" => $guid,

        "api" => array(
            "oak" => array("name" => "/module_d/oakdf", "param" => array("dsaf" => "32111321")),
            "cd" => array("name" => "/module_e/oakdfff", "param" => array("codo" => "f11ds")),
        )
    ,
    );
    $data_string = "params=" . urlencode(json_encode($data)) . "&guid=" . $guid;

    $ch = curl_init('http://127.0.0.1:9566/api/multinoresult');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Connection: Keep-Alive',
            'Keep-Alive: 300',
        )
    );

    $result = curl_exec($ch);
    var_dump(json_decode($result, true));

    $time = bcsub(microtime(true), $time, 5);
    if ($time > $maxrequest) {
        $maxrequest = $time;
    }
    echo $i . " cost:" . $time . PHP_EOL;
    //var_dump($ret);
}
echo "max:" . $maxrequest . PHP_EOL;

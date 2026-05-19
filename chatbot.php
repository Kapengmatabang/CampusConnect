<?php
$apiKey = "YOUR_OPENAI_API_KEY"; // 🔴 PUT YOUR KEY HERE

if(isset($_POST['message'])){
    $message = $_POST['message'];

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "You are a helpful campus assistant for students. Answer clearly and friendly."],
            ["role" => "user", "content" => $message]
        ]
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    echo $result["choices"][0]["message"]["content"];
}
?>
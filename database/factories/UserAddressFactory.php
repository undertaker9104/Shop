<?php

use Faker\Generator as Faker;

$factory->define(App\Models\UserAddress::class, function (Faker $faker) {
    $addresses = [
        ['台灣省','台北市','士林區'],
        ['台灣省','新北市','新店區'],
        ['台灣省','桃園市','龜山區'],
        ['台灣省','台北市','淡水區']
    ];

    $address = $faker->randomElement($addresses);
    return [
        'province'      => $address[0],
        'city'          => $address[1],
        'district'      => $address[2],
        'address'       => sprintf('第%d街道第%d号', $faker->randomNumber(2), $faker->randomNumber(3)),
        'zip'           => $faker->postcode,
        'contact_name'  => $faker->name,
        'contact_phone' => $faker->phoneNumber,
    ];
});

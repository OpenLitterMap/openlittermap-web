<?php

namespace App\Models;

final class LitterTags {
    private $json = '
{
    "smoking": {
        "class": "Smoking",
        "id_table": "smoking_id",
        "table": "smoking",
        "total": "total_smoking"
    },
    "alcohol": {
        "class": "Alcohol",
        "id_table": "alcohol_id",
        "table": "alcohol",
        "total": "total_alcohol"
    },
    "coffee": {
        "class": "Coffee",
        "id_table": "coffee_id",
        "total": "total_coffee",
        "table": "coffee"
    },
    "food": {
        "class": "Food",
        "id_table": "food_id",
        "table": "food",
        "total": "total_food"
    },
    "softdrinks": {
        "class": "SoftDrinks",
        "id_table": "softdrinks_id",
        "table": "soft_drinks",
        "total": "total_softdrinks"
    },
    "sanitary": {
        "class": "Sanitary",
        "id_table": "sanitary_id",
        "table": "sanitary",
        "total": "total_sanitary"
    },
    "other": {
        "class": "Other",
        "id_table": "other_id",
        "table": "others",
        "total": "total_other"
    },
    "coastal": {
        "class": "Coastal",
        "id_table": "coastal_id",
        "table": "coastal",
        "total": "total_coastal"
    },
    "art": {
        "class": "Art",
        "id_table": "art_id",
        "table": "arts",
        "total": "total_art"
    },
    "brands": {
        "class": "Brand",
        "id_table": "brands_id",
        "table": "brands",
        "total": "total_brands"
    },
    "trashdog": {
        "class": "TrashDog",
        "id_table": "trashdog_id",
        "table": "trashdog",
        "total": "total_trashdog"
    },
    "dumping": {
        "id_table": "dumping_id",
        "table": "dumping",
        "class": "Dumping",
        "total": "total_dumping"
    },
    "industrial": {
        "id_table": "industrial_id",
        "table": "industrial",
        "class": "Industrial",
        "total": "total_industrial"
    },
    "dogshit": {
        "id_table": "dogshit_id",
        "table": "dogshit",
        "class": "Dogshit",
        "total": "total_dogshit"
    }
}';

    private $jsonDecoded = null;

    public static function INSTANCE ()
    {
        static $inst = null;
        if ($inst === null) $inst = new LitterTags();
        return $inst;
    }

    public function __construct ()
    {
        $this->jsonDecoded = json_decode($this->json);
    }

    public function getDecodedJSON ()
    {
        return $this->jsonDecoded;
    }
}

<?php

namespace App;

// This is used when adding bounding boxes to images to train the OpenLitterAi
// last tag = poo_in_bag => 211
// Every category could be category -> 1:n

final class Litterrata {
    private $json = '
{
    "categories": {
        "smoking": "1",
        "alcohol": "2",
        "coffee": "3",
        "food": "4",
        "softdrinks": "5",
        "sanitary": "6",
        "other": "7",
        "coastal": "8",
        "dumping": "9",
        "industrial": "10",
        "dogshit": "11"
    },
    "smoking": {
        "butts": "1",
        "lighters": "2",
        "cigaretteBox": "3",
        "tobaccoPouch": "4",
        "skins": "5",
        "smoking_plastic": "6",
        "filters": "7",
        "filterbox": "8",
        "vape_pen": "9",
        "vape_oil": "10",
        "smokingOther": "11"
    },
    "alcohol": {
        "beerCan": "12",
        "beerBottle": "13",
        "spiritBottle": "14",
        "wineBottle": "15",
        "brokenGlass": "16",
        "bottleTops": "17",
        "paperCardAlcoholPackaging": "18",
        "plasticAlcoholPackaging": "19",
        "six_pack_rings": "20",
        "alcohol_plastic_cups": "21",
        "pint": "22",
        "alcoholOther": "23"
    },
    "coffee": {
        "coffeeCups": "24",
        "coffeeLids": "25",
        "coffeeOther": "26"
    },
    "food": {
        "sweetWrappers": "27",
        "paperFoodPackaging": "28",
        "plasticFoodPackaging": "29",
        "plasticCutlery": "30",
        "crisp_small": "31",
        "crisp_large": "32",
        "styrofoam_plate": "33",
        "styrofoam_plate": "34",
        "napkins": "35",
        "sauce_packet": "36",
        "glass_jar": "37",
        "glass_jar_lid": "38",
        "pizza_box": "39",
        "aluminium_foil": "40",
        "foodOther": "41"
    },
    "softdrinks": {
        "waterBottle": "42",
        "fizzyDrinkBottle": "43",
        "tinCan": "44",
        "pullring": "45",
        "bottleLid": "46",
        "bottleLabel": "47",
        "sportsDrink": "48",
        "straws": "49",
        "strawpacket": "50",
        "plastic_cups": "51",
        "plastic_cup_tops": "52",
        "milk_bottle": "53",
        "milk_carton": "54",
        "paper_cups": "55",
        "juice_cartons": "56",
        "juice_bottles": "57",
        "juice_packet": "58",
        "ice_tea_bottles": "59",
        "ice_tea_can": "60",
        "energy_can": "61",
        "styro_cup": "62",
        "softDrinkOther": "63"
    },
    "sanitary": {
        "gloves": "64",
        "facemask": "65",
        "condoms": "66",
        "nappies": "67",
        "menstral": "68",
        "deodorant": "69",
        "ear_swabs": "70",
        "tooth_pick": "71",
        "tooth_brush": "72",
        "wetwipes": "73",
        "sanitaryOther": "74"
    },
    "other": {
        "dump": "75",
        "random_litter": "76",
        "dogshit": "77",
        "pooinbag": "78",
        "plastic": "79",
        "life_buoy": "80",
        "traffic_cone": "81",
        "automobile": "82",
        "balloons": "83",
        "batteries": "84",
        "clothing": "85",
        "elec_small": "86",
        "elec_large": "87",
        "metal": "88",
        "plastic_bags": "89",
        "election_posters": "90",
        "forsale_posters": "91",
        "books": "92",
        "magazine": "93",
        "paper": "94",
        "stationary": "95",
        "washing_up": "96",
        "hair_tie": "97",
        "ear_plugs": "98",
        "bags_litter": "99",
        "cable_tie": "100",
        "tyre": "101",
        "overflowing_bins": "102",
        "other": "103"
    },
    "coastal": {
        "microplastics": "104",
        "mediumplastics": "105",
        "macroplastics": "106",
        "rope_small": "107",
        "rope_medium": "108",
        "rope_large": "109",
        "fishing_gear_nets": "110",
        "ghost_nets": "209",
        "buoys": "111",
        "degraded_plasticbottle": "112",
        "degraded_plasticbag": "113",
        "degraded_straws": "114",
        "degraded_lighters": "115",
        "balloons": "116",
        "lego": "117",
        "shotgun_cartridges": "118",
        "styro_small": "119",
        "styro_medium": "120",
        "styro_large": "121",
        "coastal_other": "122"
    },
    "brands": {
        "adidas": "123",
        "amazon": "124",
        "aldi": "125",
        "apple": "126",
        "applegreen": "127",
        "asahi": "128",
        "avoca": "129",
        "ballygowan": "130",
        "bewleys": "131",
        "brambles": "132",
        "budweiser": "133",
        "bulmers": "134",
        "burgerking": "135",
        "butlers": "136",
        "cadburys": "137",
        "cafe_nero": "138",
        "camel": "139",
        "carlsberg": "140",
        "centra": "141",
        "circlek": "142",
        "coke": "143",
        "colgate": "144",
        "coles": "145",
        "corona": "146",
        "costa": "147",
        "doritos": "148",
        "drpepper": "149",
        "dunnes": "150",
        "duracell": "151",
        "durex": "152",
        "esquires": "153",
        "evian": "154",
        "fosters": "155",
        "frank_and_honest": "156",
        "fritolay": "157",
        "gatorade": "158",
        "gillette": "159",
        "guinness": "160",
        "haribo": "161",
        "heineken": "162",
        "insomnia": "163",
        "kellogs": "164",
        "kfc": "165",
        "lego": "166",
        "lidl": "167",
        "lindenvillage": "168",
        "lolly_and_cookes": "169",
        "loreal": "170",
        "lucozade": "171",
        "nero": "172",
        "nescafe": "173",
        "nestle": "174",
        "marlboro": "175",
        "mars": "176",
        "mcdonalds": "177",
        "nike": "178",
        "obriens": "179",
        "pepsi": "180",
        "powerade": "181",
        "redbull": "182",
        "ribena": "183",
        "samsung": "184",
        "sainsburys": "185",
        "spar": "186",
        "starbucks": "187",
        "stella": "188",
        "subway": "189",
        "supermacs": "190",
        "supervalu": "191",
        "tayto": "192",
        "tesco": "193",
        "thins": "194",
        "volvic": "195",
        "waitrose": "196",
        "wilde_and_greene": "197",
        "woolworths": "198",
        "wrigleys": "199"
    },
    "dumping": {
        "small": "200",
        "medium": "201",
        "large": "202"
    },
    "industrial": {
        "oil": "203",
        "chemical": "204",
        "industrial_plastic": "205",
        "bricks": "206",
        "tape": "207",
        "industrial_other": "208"
    },
    "dogshit": {
        "poo": "210",
        "poo_in_bag": "211"
    }
}';

    private $jsonDecoded  = null;

    public static function INSTANCE ()
    {
        static $inst = null;
        if ($inst === null) $inst = new Litterrata();
        return $inst;
    }

    public function __construct ()
    {
        $this->jsonDecoded = json_decode($this->json);
    }

    public function getDecodedJSON(){ return $this->jsonDecoded; }
}

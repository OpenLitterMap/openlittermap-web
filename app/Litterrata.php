<?php

namespace App;

final class Litterrata{
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Cigarettes/Butts": {
                "col": "butts"
            },
            "Lighters": {
                "col": "lighters"
            },
            "Cigarette Box": {
                "col": "cigaretteBox"
            },
            "Tobacco Pouch": {
                "col": "tobaccoPouch"
            },
            "Rolling Papers": {
                "col": "skins"
            },
            "Plastic Packaging": {
                "col": "smoking_plastic"
            },
            "Filters": {
                "col": "filters"
            },
            "Filter Box": {
                "col": "filterbox"
            },
            "Vape pen": {
                "col": "vape_pen"
            },
            "Vape oil": {
                "col": "vape_oil"
            },
            "Smoking-Other": {
                "col": "smokingOther"
            }
        }
    },
    "Alcohol": {
        "table": "alcohol",
        "id": "alcohol_id",
        "class": "Alcohol",
        "total": "total_alcohol",
        "types": {
            "Beer Cans": {
                "col": "beerCan"
            },
            "Beer Bottles": {
                "col": "beerBottle"
            },
            "Spirit Bottles": {
                "col": "spiritBottle"
            },
            "Wine Bottles": {
                "col": "wineBottle"
            },
            "Broken Glass": {
                "col": "brokenGlass"
            },
            "Beer bottle tops": {
                "col": "bottleTops"
            },
            "Paper Packaging": {
                "col": "paperCardAlcoholPackaging"
            },
            "Plastic Packaging": {
                "col": "plasticAlcoholPackaging"
            },
            "Six-pack rings": {
                "col": "six_pack_rings"
            },
            "Plastic Cups": {
                "col": "alcohol_plastic_cups"
            },
            "Pint Glass": {
                "col": "pint"
            },
            "Alcohol-Other": {
                "col": "alcoholOther"
            }
        }
    },
    "Coffee": {
        "id": "coffee_id",
        "table": "coffee",
        "class": "Coffee",
        "total": "total_coffee",
        "types": {
            "Coffee Cups": {
                "col": "coffeeCups"
            },
            "Coffee Lids": {
                "col": "coffeeLids"
            },
            "Coffee-other": {
                "col": "coffeeOther"
            }
        }
    },
    "Food": {
        "id": "food_id",
        "table": "food",
        "class": "Food",
        "total": "total_food",
        "types": {
            "Sweet Wrappers": {
                "col": "sweetWrappers"
            },
            "Paper/Cardboard Packaging": {
                "col": "paperFoodPackaging"
            },
            "Plastic Packaging": {
                "col": "plasticFoodPackaging"
            },
            "Plastic Cutlery": {
                "col": "plasticCutlery"
            },
            "Crisp/Chip Packet (small)": {
                "col": "crisp_small"
            },
            "Crisp/Chip Packet (large)": {
                "col": "crisp_large"
            },
            "Styrofoam": {
                "col": "styrofoam_plate"
            },
            "Styrofoam Plate": {
                "col": "styrofoam_plate"
            },
            "Napkins": {
                "col": "napkins"
            },
            "Sauce Packet": {
                "col": "sauce_packet"
            },
            "Glass Jar": {
                "col": "glass_jar"
            },
            "Glass Jar Lid": {
                "col": "glass_jar_lid"
            },
            "Pizza Box": {
                "col": "pizza_box"
            },
            "Aluminium Foil": {
                "col": "aluminium_foil"
            },
            "Food-other": {
                "col": "foodOther"
            }
        }
    },
    "SoftDrinks": {
        "id": "softdrinks_id",
        "table": "soft_drinks",
        "class": "SoftDrinks",
        "total": "total_softdrinks",
        "types": {
            "Plastic Water bottle": {
                "col": "waterBottle"
            },
            "Plastic Fizzy Drink bottle": {
                "col": "fizzyDrinkBottle"
            },
            "Can": {
                "col": "tinCan"
            },
            "Pull-ring": {
                "col": "pullring"
            },
            "Bottle Tops": {
                "col": "bottleLid"
            },
            "Bottle Labels": {
                "col": "bottleLabel"
            },
            "Sports Drink bottle": {
                "col": "sportsDrink"
            },
            "Straws": {
                "col": "straws"
            },
            "Straw Packaging": {
                "col": "strawpacket"
            },
            "Plastic Cups": {
                "col": "plastic_cups"
            },
            "Plastic Cup Tops": {
                "col": "plastic_cup_tops"
            },
            "Milk Bottle": {
                "col": "milk_bottle"
            },
            "Milk Carton": {
                "col": "milk_carton"
            },
            "Paper Cups": {
                "col": "paper_cups"
            },
            "Juice Cartons": {
                "col": "juice_cartons"
            },
            "Juice Bottles": {
                "col": "juice_bottles"
            },
            "Juice Packet": {
                "col": "juice_packet"
            },
            "Ice Tea Bottles": {
                "col": "ice_tea_bottles"
            },
            "Ice Tea Can": {
                "col": "ice_tea_can"
            },
            "Energy Can": {
                "col": "energy_can"
            },
            "Styrofoam Cup": {
                "col": "styro_cup"
            },
            "Soft Drink (other)": {
                "col": "softDrinkOther"
            }
        }
    },
    "Drugs": {
        "id": "drugs_id",
        "table": "drugs",
        "class": "Drugs",
        "total": "total_drugs",
        "types": {
            "Needles": {
                "col": "needles"
            },
            "Citric Acid Wipes": {
                "col": "wipes"
            },
            "Needle Tops": {
                "col": "tops"
            },
            "Needle Packaging": {
                "col": "packaging"
            },
            "Sterile Water bottle": {
                "col": "waterbottle"
            },
            "Metal Spoons": {
                "col": "spoons"
            },
            "Needle Bin": {
                "col": "needlebin"
            },
            "Empty Syringe Barrell": {
                "col": "barrels"
            },
            "Tinfoil": {
                "col": "usedtinfoil"
            },
            "Full Package": {
                "col": "fullpackage"
            },
            "Baggie": {
                "col": "baggie"
            },
            "Crack Pipes": {
                "col": "crack_pipes"
            },
            "Drugs (other)": {
                "col": "drugsOther"
            }
        }
    },
    "Sanitary": {
        "id": "sanitary_id",
        "table": "sanitary",
        "class": "Sanitary",
        "total": "total_sanitary",
        "types": {
            "Gloves": {
                "col": "gloves"
            },
            "Facemask": {
                "col": "facemask"
            },
            "Condoms": {
                "col": "condoms"
            },
            "Nappies": {
                "col": "nappies"
            },
            "Menstral": {
                "col": "menstral"
            },
            "Deodorant": {
                "col": "deodorant"
            },
            "Ear Swabs": {
                "col": "ear_swabs"
            },
            "Tooth Pick": {
                "col": "tooth_pick"
            },
            "Tooth Brush": {
                "col": "tooth_brush"
            },
            "Wet Wipes": {
                "col": "wetwipes"
            },
            "Sanitary (other)": {
                "col": "sanitaryOther"
            }
        }
    },
    "Other": {
        "id": "other_id",
        "table": "others",
        "class": "Other",
        "total": "total_other",
        "types": {
            "Illegal Dumping": {
                "col": "dump"
            },
            "Random Litter": {
                "col": "random_litter"
            },
            "Pet Surprise": {
                "col": "dogshit"
            },
            "Pet Surprise in a Bag": {
                "col": "pooinbag"
            },
            "Unidentified Plastic": {
                "col": "plastic"
            },
            "Life Buoy": {
                "col": "life_buoy"
            },
            "Traffic Cone": {
                "col": "traffic_cone"
            },
            "Automobile": {
                "col": "automobile"
            },
            "Balloons": {
                "col": "balloons"
            },
            "Batteries": {
                "col": "batteries"
            },
            "Clothing": {
                "col": "clothing"
            },
            "Electric small": {
                "col": "elec_small"
            },
            "Electric large": {
                "col": "elec_large"
            },
            "Metal Object": {
                "col": "metal"
            },
            "Plastic Bags": {
                "col": "plastic_bags"
            },
            "Election Posters": {
                "col": "election_posters"
            },
            "For Sale Posters": {
                "col": "forsale_posters"
            },
            "Books": {
                "col": "books"
            },
            "Magazines": {
                "col": "magazine"
            },
            "Paper": {
                "col": "paper"
            },
            "Stationery": {
                "col": "stationary"
            },
            "Washing-up Bottle": {
                "col": "washing_up"
            },
            "Hair Tie": {
                "col": "hair_tie"
            },
            "Ear Plugs (music)": {
                "col": "ear_plugs"
            },
            "Bags of Litter": {
                "col": "bags_litter"
            },
            "Cable Tie": {
                "col": "cable_tie"
            },
            "Tyre": {
                "col": "tyre"
            },
            "Overflowing Bins": {
                "col": "overflowing_bins"
            },
            "Other (other)": {
                "col": "other"
            }
        }
    },
    "Coastal": {
        "id": "coastal_id",
        "table": "costal",
        "class": "Coastal",
        "total": "total_coastal",
        "types": {
            "Microplastics": {
                "col": "microplastics"
            },
            "Mediumplastics": {
                "col": "mediumplastics"
            },
            "Macroplastics": {
                "col": "macroplastics"
            },
            "Rope small": {
                "col": "rope_small"
            },
            "Rope medium": {
                "col": "rope_medium"
            },
            "Rope large": {
                "col": "rope_large"
            },
            "Fishing gear/nets": {
                "col": "fishing_gear_nets"
            },
            "Buoys": {
                "col": "buoys"
            },
            "Degraded Plastic Bottle": {
                "col": "degraded_plasticbottle"
            },
            "Degraded Plastic Bag": {
                "col": "degraded_plasticbag"
            },
            "Degraded Drinking Straws": {
                "col": "degraded_straws"
            },
            "Degraded Lighters": {
                "col": "degraded_lighters"
            },
            "Balloons": {
                "col": "balloons"
            },
            "Lego": {
                "col": "lego"
            },
            "Shotgun Cartridges": {
                "col": "shotgun_cartridges"
            },
            "Styrofoam small": {
                "col": "styro_small"
            },
            "Styrofoam medium": {
                "col": "styro_medium"
            },
            "Styrofoam large": {
                "col": "styro_large"
            },
            "Coastal (other)": {
                "col": "coastal_other"
            }
        }
    },
    "Marine": {
        "id": "coastal_id",
        "table": "costal",
        "class": "Coastal",
        "total": "total_coastal",
        "types": {
            "Microplastics": {
                "col": "microplastics"
            },
            "Mediumplastics": {
                "col": "mediumplastics"
            },
            "Macroplastics": {
                "col": "macroplastics"
            },
            "Rope small": {
                "col": "rope_small"
            },
            "Rope medium": {
                "col": "rope_medium"
            },
            "Rope large": {
                "col": "rope_large"
            },
            "Fishing gear/nets": {
                "col": "fishing_gear_nets"
            },
            "Buoys": {
                "col": "buoys"
            },
            "Degraded Plastic Bottle": {
                "col": "degraded_plasticbottle"
            },
            "Degraded Plastic Bag": {
                "col": "degraded_plasticbag"
            },
            "Degraded Drinking Straws": {
                "col": "degraded_straws"
            },
            "Degraded Lighters": {
                "col": "degraded_lighters"
            },
            "Balloons": {
                "col": "balloons"
            },
            "Lego": {
                "col": "lego"
            },
            "Shotgun Cartridges": {
                "col": "shotgun_cartridges"
            },
            "Styrofoam small": {
                "col": "styro_small"
            },
            "Styrofoam medium": {
                "col": "styro_medium"
            },
            "Styrofoam large": {
                "col": "styro_large"
            },
            "Coastal (other)": {
                "col": "coastal_other"
            }
        }
    },
    "Art": {
        "id": "art_id",
        "table": "arts",
        "class": "Art",
        "total": "total_art",
        "types": {
            "Item": {
                "col": "item"
            }
        }
    },
    "Brands": {
        "id": "brands_id",
        "table": "brands",
        "class": "Brand",
        "total": "total_brands",
        "types": {
            "Adidas": {
                "col": "adidas"
            },
            "Amazon": {
                "col": "amazon"
            },
            "Aldi": {
                "col": "aldi"
            },
            "Apple": {
                "col": "apple"
            },
            "Applegreen": {
                "col": "applegreen"
            },
            "Asahi": {
                "col": "asahi"
            },
            "Avoca": {
                "col": "avoca"
            },
            "Ballygowan": {
                "col": "ballygowan"
            },
            "Bewleys": {
                "col": "bewleys"
            },
            "Brambles": {
                "col": "brambles"
            },
            "Budweiser": {
                "col": "budweiser"
            },
            "Bulmers": {
                "col": "bulmers"
            },
            "BurgerKing": {
                "col": "burgerking"
            },
            "Butlers": {
                "col": "butlers"
            },
            "Cadburys": {
                "col": "cadburys"
            },
            "Cafe-Nero": {
                "col": "cafe_nero"
            },
            "Camel": {
                "col": "camel"
            },
            "Carlsberg": {
                "col": "carlsberg"
            },
            "Centra": {
                "col": "centra"
            },
            "CircleK": {
                "col": "circlek"
            },
            "Coca-Cola": {
                "col": "coke"
            },
            "Colgate": {
                "col": "colgate"
            },
            "Coles": {
                "col": "coles"
            },
            "Corona": {
                "col": "corona"
            },
            "Costa": {
                "col": "costa"
            },
            "Doritos": {
                "col": "doritos"
            },
            "DrPepper": {
                "col": "drpepper"
            },
            "Dunnes": {
                "col": "dunnes"
            },
            "Duracell": {
                "col": "duracell"
            },
            "Durex": {
                "col": "durex"
            },
            "Esquires": {
                "col": "esquires"
            },
            "Evian": {
                "col": "evian"
            },
            "Fosters": {
                "col": "fosters"
            },
            "Frank-and-Honest": {
                "col": "frank_and_honest"
            },
            "Frito-Lay": {
                "col": "fritolay"
            },
            "Gatorade": {
                "col": "gatorade"
            },
            "Gillette": {
                "col": "gillette"
            },
            "Guinness": {
                "col": "guinness"
            },
            "Haribo": {
                "col": "haribo"
            },
            "Heineken": {
                "col": "heineken"
            },
            "Insomnia": {
                "col": "insomnia"
            },
            "Kellogs": {
                "col": "kellogs"
            },
            "KFC": {
                "col": "kfc"
            },
            "Lego": {
                "col": "lego"
            },
            "Lidl": {
                "col": "lidl"
            },
            "LindenVillage": {
                "col": "lindenvillage"
            },
            "Lolly-and-Cookes": {
                "col": "lolly_and_cookes"
            },
            "Loreal": {
                "col": "loreal"
            },
            "Lucozade": {
                "col": "lucozade"
            },
            "Nero": {
                "col": "nero"
            },
            "Nescafe": {
                "col": "nescafe"
            },
            "Nestle": {
                "col": "nestle"
            },
            "Marlboro": {
                "col": "marlboro"
            },
            "Mars": {
                "col": "mars"
            },
            "McDonalds": {
                "col": "mcdonalds"
            },
            "Nike": {
                "col": "nike"
            },
            "O-Briens": {
                "col": "obriens"
            },
            "Pepsi": {
                "col": "pepsi"
            },
            "Powerade": {
                "col": "powerade"
            },
            "Redbull": {
                "col": "redbull"
            },
            "Ribena": {
                "col": "ribena"
            },
            "Samsung": {
                "col": "samsung"
            },
            "Sainsburys": {
                "col": "sainsburys"
            },
            "Spar": {
                "col": "spar"
            },
            "Starbucks": {
                "col": "starbucks"
            },
            "Stella": {
                "col": "stella"
            },
            "Subway": {
                "col": "subway"
            },
            "Supermacs": {
                "col": "supermacs"
            },
            "Supervalu": {
                "col": "supervalu"
            },
            "Tayto": {
                "col": "tayto"
            },
            "Tesco": {
                "col": "tesco"
            },
            "Thins": {
                "col": "thins"
            },
            "Volvic": {
                "col": "volvic"
            },
            "Waitrose": {
                "col": "waitrose"
            },
            "Wilde-and-Greene": {
                "col": "wilde_and_greene"
            },
            "Woolworths": {
                "col": "woolworths"
            },
            "Wrigleys": {
                "col": "wrigleys"
            }
        }
    },
    "TrashDog": {
        "id": "trashdog_id",
        "table": "trash_dogs",
        "class": "TrashDog",
        "total": "total_trashdog",
        "types": {
            "TrashDog": {
                "total": "totalTrashDog",
                "col": "trashdog"
            },
            "Littercat": {
                "col": "littercat"
            },
            "LitterDuck": {
                "col": "duck"
            }
        }
    },
    "Dumping": {
        "id": "dumping_id",
        "table": "dumping",
        "class": "Dumping",
        "total": "total_dumping",
        "types": {
            "Small": {
                "col": "small"
            },
            "Medium": {
                "col": "medium"
            },
            "Large": {
                "col": "large"
            }
        }
    },
    "Industrial": {
        "id": "industrial_id",
        "table": "industrial",
        "class": "Industrial",
        "total": "total_industrial",
        "types": {
            "Oil": {
                "col": "oil"
            },
            "Chemical": {
                "col": "chemical"
            },
            "Plastic": {
                "col": "industrial_plastic"
            },
            "Bricks": {
                "col": "bricks"
            },
            "Tape": {
                "col": "tape"
            },
            "Other": {
                "col": "industrial_other"
            }
        }
    }

}';

    private $jsonDecoded  = null;
    public static function INSTANCE(){
        static $inst = null;
        if ($inst === null) $inst = new Litterrata();
        return $inst;
    }

    public function __construct(){
        $this->jsonDecoded = json_decode($this->json);
    }
    public function getDecodedJSON(){ return $this->jsonDecoded; }
}

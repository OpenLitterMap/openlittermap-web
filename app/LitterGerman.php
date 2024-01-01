<?php

namespace App;

final class LitterGerman{
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Zigaretten/Stummel": {
                "col": "butts"
            },
            "Feuerzeug": {
                "col": "lighters"
            },
            "Zigarettenschachtel": {
                "col": "cigaretteBox"
            },
            "Tabakbeutel": {
                "col": "tobaccoPouch"
            },
            "Papierchen": {
                "col": "skins"
            },
            "Plastikverpackung": {
                "col": "smoking_plastic"
            },
            "Filter": {
                "col": "filters"
            },
            "Filterschachtel": {
                "col": "filterbox"
            },
            "Rauchen (anderes)": {
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
            "Bierdosen": {
                "col": "beerCan"
            },
            "Glasflaschen (Bier)": {
                "col": "beerBottle"
            },
            "Glasflaschen (Spirituosen)": {
                "col": "spiritBottle"
            },
            "Glasflaschen (Wein)": {
                "col": "wineBottle"
            },
            "Glasscherben": {
                "col": "brokenGlass"
            },
            "Bierdeckel": {
                "col": "bottleTops"
            },
            "Papierverpackung": {
                "col": "paperCardAlcoholPackaging"
            },
            "Plastikverpackung": {
                "col": "plasticAlcoholPackaging"
            },
            "Pint Glass": {
                "col": "pint"
            },
            "Alkohol (anderes)": {
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
            "Kaffeebecher": {
                "col": "coffeeCups"
            },
            "Kaffeebecherdeckel": {
                "col": "coffeeLids"
            },
            "Kaffee (anderes)": {
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
            "Süssigkeiten Verpackung": {
                "col": "sweetWrappers"
            },
            "Papier/Karton-Verpackung": {
                "col": "paperFoodPackaging"
            },
            "Plastik-Verpackung": {
                "col": "plasticFoodPackaging"
            },
            "Plastikbesteck": {
                "col": "plasticCutlery"
            },
            "Chips Tüte (klein)": {
                "col": "crisp_small"
            },
            "Chips Tüte (gross)": {
                "col": "crisp_large"
            },
            "Styropor": {
                "col": "styrofoam_plate"
            },
            "Servietten": {
                "col": "napkins"
            },
            "Sossen-Verpackung": {
                "col": "sauce_packet"
            },
            "Einmachglas": {
                "col": "glass_jar"
            },
            "Einmachglasdeckel": {
                "col": "glass_jar_lid"
            },
            "Essen (anderes)": {
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
            "Plastikwasserflasche": {
                "col": "waterBottle"
            },
            "Plastikflasche sprudelnd": {
                "col": "fizzyDrinkBottle"
            },
            "Können": {
                "col": "tinCan"
            },
            "Metallring": {
                "col": "pullring"
            },
            "Flaschendeckel": {
                "col": "bottleLid"
            },
            "Flaschenetikette": {
                "col": "bottleLabel"
            },
            "Sportgetränkeflasche": {
                "col": "sportsDrink"
            },
            "Trinkhalm": {
                "col": "straws"
            },
            "Getränkverpackung": {
                "col": "strawpacket"
            },
            "Plastikbecher": {
                "col": "plastic_cups"
            },
            "Plastikbecherdeckel": {
                "col": "plastic_cup_tops"
            },
            "Milchflasche": {
                "col": "milk_bottle"
            },
            "Milchkarton": {
                "col": "milk_carton"
            },
            "Papierbecher": {
                "col": "paper_cups"
            },
            "Fruchtsaftkarton": {
                "col": "juice_cartons"
            },
            "Fruchtsaftflasche": {
                "col": "juice_bottles"
            },
            "Fruchtsaftverpackung": {
                "col": "juice_packet"
            },
            "Eisteeflasche": {
                "col": "ice_tea_bottles"
            },
            "Eisteedose": {
                "col": "ice_tea_can"
            },
            "Energydrink Dose": {
                "col": "energy_can"
            },
            "Styrofoam Becher": {
                "col": "styro_cup"
            },
            "Getränke (anderes)": {
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
            "Kondome": {
                "col": "condoms"
            },
            "Windeln": {
                "col": "nappies"
            },
            "Tampon": {
                "col": "menstral"
            },
            "Deodorant": {
                "col": "deodorant"
            },
            "Wattestäbchen": {
                "col": "ear_swabs"
            },
            "Zahnstocher": {
                "col": "tooth_pick"
            },
            "Zahnbürste": {
                "col": "tooth_brush"
            },
            "Feuchttücher": {
                "col": "wetwipes"
            },
            "Hygiene (anderes)": {
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
            "Illegales Verschrotten": {
                "col": "dump"
            },
            "Hundescheisse": {
                "col": "dogshit"
            },
            "Scheisse in Tüte": {
                "col": "pooinbag"
            },
            "Unbekannter Plastik": {
                "col": "plastic"
            },
            "Rettungsreifen": {
                "col": "life_buoy"
            },
            "Leitkegel": {
                "col": "traffic_cone"
            },
            "Autoteile": {
                "col": "automobile"
            },
            "Luftballone": {
                "col": "balloons"
            },
            "Batterien": {
                "col": "batteries"
            },
            "Klamotten": {
                "col": "clothing"
            },
            "Elektronikschrott (klein)": {
                "col": "elec_small"
            },
            "Elektronikschrott (gross)": {
                "col": "elec_large"
            },
            "Metall Objekt": {
                "col": "metal"
            },
            "Plastiktasche/Handtasche": {
                "col": "plastic_bags"
            },
            "Wahlplakate": {
                "col": "election_posters"
            },
            "Verkaufsposter": {
                "col": "forsale_posters"
            },
            "Bücher": {
                "col": "books"
            },
            "Zeitschriften": {
                "col": "magazine"
            },
            "Papier": {
                "col": "paper"
            },
            "Büromaterial": {
                "col": "stationary"
            },
            "Waschmittelflasche": {
                "col": "washing_up"
            },
            "Haargummi": {
                "col": "hair_tie"
            },
            "Kopfhörer": {
                "col": "ear_plugs"
            },
            "Weiteres (anderes)": {
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
            "Mikroplastik": {
                "col": "microplastics"
            },
            "Mediumplastik": {
                "col": "mediumplastics"
            },
            "Makroplastik": {
                "col": "macroplastics"
            },
            "Kurzes Seil": {
                "col": "rope_small"
            },
            "Mittelgrosses Seil": {
                "col": "rope_medium"
            },
            "Langes Seil": {
                "col": "rope_large"
            },
            "Fischerausrüstung/Netze": {
                "col": "fishing_gear_nets"
            },
            "Bojen": {
                "col": "buoys"
            },
            "Abgebaute, zersetzte Flasche": {
                "col": "degraded_plasticbottle"
            },
            "Abgebaute, zersetzte Tasche": {
                "col": "degraded_plasticbag"
            },
            "Abgebauter Trinkhalm": {
                "col": "degraded_straws"
            },
            "Zersetztes Feuerzeug": {
                "col": "degraded_lighters"
            },
            "Ballone": {
                "col": "balloons"
            },
            "Legoteile": {
                "col": "lego"
            },
            "Schusspatronen": {
                "col": "shotgun_cartridges"
            },
            "Styropor (klein)": {
                "col": "styro_small"
            },
            "Styropor (medium)": {
                "col": "styro_medium"
            },
            "Styropor (gross)": {
                "col": "styro_large"
            },
            "Küstenbereich (anderes)": {
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
    }

}';

    private $jsonDecoded;

    public static function INSTANCE(){
        static $inst = null;
        if ($inst === null) {
            $inst = new LitterGerman();
        }

        return $inst;
    }

    public function __construct(){
        $this->jsonDecoded = json_decode((string) $this->json);
    }

    public function getDecodedJSON(){ return $this->jsonDecoded; }
}


//// USAGE

// $jsonDecoded = Litterrata::INSTANCE()->getDecodedJSON();

// $joking = "smoking";
// $lable  = "table";
// echo $jsonDecoded->$joking->$lable . "<br />\n";
// echo $jsonDecoded->smoking->table  . "<br />\n";

//// USAGE

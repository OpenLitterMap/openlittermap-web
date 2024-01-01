<?php

namespace App;

final class LitterPolish{
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Filtr po papierosie": {
                "col": "butts"
            },
            "Zapalniczka": {
                "col": "lighters"
            },
            "Paczka po papierosach": {
                "col": "cigaretteBox"
            },
            "Paczka tytoniu": {
                "col": "tobaccoPouch"
            },
            "Emballage en plastique": { ????
                "col": "smoking_plastic"
            },
            "Filtry": {
                "col": "filters"
            },
            "Pudełko po filtrach": {
                "col": "filterbox"
            },
            "Papierosy (inne)": {
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
            "Butelka po piwie": {
                "col": "beerBottle"
            },
            "Butelka po mocniejszym alkoholu": {
                "col": "spiritBottle"
            },
            "Butelka po winie": {
                "col": "wineBottle"
            },
            "Puszka po piwie": {
                "col": "beerCan"
            },
            "Zbite szkło": {
                "col": "brokenGlass"
            },
            "Zakrętki": {
                "col": "bottleTops"
            },
            "Papierowe opakowanie po alkoholu": {
                "col": "paperCardAlcoholPackaging"
            },
            "Plastikowe opakowanie po alkoholu": {
                "col": "plasticAlcoholPackaging"
            },
            "Alkohol (inne)": {
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
            "Kubeczek po kawie": {
                "col": "coffeeCups"
            },
            "Wieczko po kawie": {
                "col": "coffeeLids"
            },
            "Kawa (inne)": {
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
            "Papierek po słodyczach": {
                "col": "sweetWrappers"
            },
            "Papierowe opakowanie po jedzeniu": {
                "col": "paperFoodPackaging"
            },
            "Plastikowe opakowanie po jedzeniu": {
                "col": "plasticFoodPackaging"
            },
            "Plastikowe sztućce": {
                "col": "plasticCutlery"
            },
            "Paczka po czipsach (mała)": {
                "col": "crisp_small"
            },
            "Paczka po czipsach (duża)": {
                "col": "crisp_large"
            },
            "Styropianowy talerz": {
                "col": "styrofoam_plate"
            },
            "Chusteczka": {
                "col": "napkins"
            },
            "Paczka po sosie": {
                "col": "sauce_packet"
            },
            "Słoik": {
                "col": "glass_jar"
            },
            "Zakrętka po słoiku": {
                "col": "glass_jar_lid"
            },
            "Jedzenie (inne)": {
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
            "Butelka po wodzie": {
                "col": "waterBottle"
            },
            "Butelka po napoju": {
                "col": "fizzyDrinkBottle"
            },
            "Puszka napoju": {
                "col": "tinCan"
            },
            "Zakrętka od butelki": {
                "col": "bottleLid"
            },
            "Etykieta butelki": {
                "col": "bottleLabel"
            },
            "Izotonik": {
                "col": "sportsDrink"
            },
            "Słomka": {
                "col": "straws"
            },
            "Plastikowy kubeczek": {
                "col": "plastic_cups"
            },
            "Wieczko po plastikowym kubeczku": {
                "col": "plastic_cup_tops"
            },
            "Butelka po mleku": {
                "col": "milk_bottle"
            },
            "Karton po mleku": {
                "col": "milk_carton"
            },
            "Papierowe kubeczki": {
                "col": "paper_cups"
            },
            "Karton po soku": {
                "col": "juice_cartons"
            },
            "Butelka po soku": {
                "col": "juice_bottles"
            },
            "Paczka po soku": {
                "col": "juice_packet"
            },
            "Butelka Ice Tea": {
                "col": "ice_tea_bottles"
            },
            "puszka Ice Tea": {
                "col": "ice_tea_can"
            },
            "Puszka po energetyku": {
                "col": "energy_can"
            },
            "Styropianowy kubeczek": {
                "col": "styro_cup"
            },
            "Napoje (Inne)": {
                "col": "softDrinkOther"
            }
        }
    },
    "Sanitary": {
        "id": "sanitary_id",
        "table": "sanitary",
        "class": "Sanitary",
        "total": "total_sanitary",
        "types": {
            "Prezerwatywy": {
                "col": "condoms"
            },
            "Pieluchy": {
                "col": "nappies"
            },
            "Podpaski": {
                "col": "menstral"
            },
            "Dezodorant": {
                "col": "deodorant"
            },
            "Patyczki do uszu": {
                "col": "ear_swabs"
            },
            "Patyczki do zębów": {
                "col": "tooth_pick"
            },
            "Szczoteczka do zębów": {
                "col": "tooth_brush"
            },
            "Higiena (inne)": {
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
            "Psia kupa": {
                "col": "dogshit"
            },
            "Plastique non identifiable": {
                "col": "plastic"
            },
            "Zaśmiecanie": {
                "col": "dump"
            },
            "Przedmiot metalowy": {
                "col": "metal"
            },
            "Plastikowa siatka": {
                "col": "plastic_bags"
            },
            "Postery wyborcze": {
                "col": "election_posters"
            },
            "Postery na sprzedaż": {
                "col": "forsale_posters"
            },
            "książki": {
                "col": "books"
            },
            "Magazyny": {
                "col": "magazine"
            },
            "Papier": {
                "col": "paper"
            },
            "Papiery biurowe": {
                "col": "stationary"
            },
            "Detergenty do prania": {
                "col": "washing_up"
            },
            "Gumka do włosów": {
                "col": "hair_tie"
            },
            "Zatyczki do uszu": {
                "col": "ear_plugs"
            },
            "Inne (inne)": {
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
            "Microplastiki": {
                "col": "microplastics"
            },
            "Średnie tworzywa sztuczne": {
                "col": "mediumplastics"
            },
            "makroplastiki": {
                "col": "macroplastics"
            },
            "Lina (krótka)": {
                "col": "rope_small"
            },
            "Lina (Średnia)": {
                "col": "rope_medium"
            },
            "Lina (Duża)": {
                "col": "rope_large"
            },
            "Siatka rybacka": {
                "col": "fishing_gear_nets"
            },
            "Boja": {
                "col": "buoys"
            },
            "zdegradowana plastikowa butelka ": {
                "col": "degraded_plasticbottle"
            },
            "zdegradowana plastikowa siatka": {
                "col": "degraded_plasticbag"
            },
            "zdegradowana plastikowa słomka": {
                "col": "degraded_straws"
            },
            "zdegradowana plastikowa zapalniczka": {
                "col": "degraded_lighters"
            },
            "Balony": {
                "col": "balloons"
            },
            "Lego": {
                "col": "lego"
            },
            "Kartridż do broni": {
                "col": "shotgun_cartridges"
            },
            "Styropian (Mały)": {
                "col": "styro_small"
            },
            "Styropian (Średni)": {
                "col": "styro_medium"
            },
            "Styropian (Duży)": {
                "col": "styro_large"
            },
            "przybrzeżny (inne)": {
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
                "col": "cardburys"
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
            $inst = new LitterFrench();
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

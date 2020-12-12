<?php

namespace App;

final class LitterFrench{
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Mégot de cigarette": {
                "col": "butts"
            },
            "Briquet": {
                "col": "lighters"
            },
            "Boîte de cigarettes": {
                "col": "cigaretteBox"
            },
            "Boîte/Sachet de tabac": {
                "col": "tobaccoPouch"
            },
            "Emballage en plastique": {
                "col": "smoking_plastic"
            },
            "Filtre": {
                "col": "filters"
            },
            "Boîte de filtres": {
                "col": "filterbox"
            },
            "Cigarette (autre)": {
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
            "Bouteille en verre (bière)": {
                "col": "beerBottle"
            },
            "Bouteille en verre (alcool fort)": {
                "col": "spiritBottle"
            },
            "Bouteille en verre (vin)": {
                "col": "wineBottle"
            },
            "Cannette de bière": {
                "col": "beerCan"
            },
            "Verre brisé": {
                "col": "brokenGlass"
            },
            "Bouchon de bouteille": {
                "col": "bottleTops"
            },
            "Emballage en papier": {
                "col": "paperCardAlcoholPackaging"
            },
            "Emballage plastique": {
                "col": "plasticAlcoholPackaging"
            },
            "Alcool (autre)": {
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
            "Tasse de café": {
                "col": "coffeeCups"
            },
            "Couvercle": {
                "col": "coffeeLids"
            },
            "Café (autre)": {
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
            "Emballage de bonbons": {
                "col": "sweetWrappers"
            },
            "Emballage en papier/carton": {
                "col": "paperFoodPackaging"
            },
            "Emballage en plastique": {
                "col": "plasticFoodPackaging"
            },
            "Couverts en plastique": {
                "col": "plasticCutlery"
            },
            "Paquet de chips (petit)": {
                "col": "crisp_small"
            },
            "Paquet de chips (grand)": {
                "col": "crisp_large"
            },
            "Polystyrène": {
                "col": "styrofoam_plate"
            },
            "Serviette en papier": {
                "col": "napkins"
            },
            "Paquet de sauce": {
                "col": "sauce_packet"
            },
            "Bocal en verre": {
                "col": "glass_jar"
            },
            "Couvercle de bocal": {
                "col": "glass_jar_lid"
            },
            "Nourriture (autre)": {
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
            "Bouteille d`eau en plastique": {
                "col": "waterBottle"
            },
            "Bouteille de soda": {
                "col": "fizzyDrinkBottle"
            },
            "Cannette de soda": {
                "col": "tinCan"
            },
            "Bouchon de bouteille": {
                "col": "bottleLid"
            },
            "Label pada Botol": {
                "col": "bottleLabel"
            },
            "Bouteille de boissons énergétique": {
                "col": "sportsDrink"
            },
            "Pailles": {
                "col": "straws"
            },
            "Tasse en plastique": {
                "col": "plastic_cups"
            },
            "Couvercle de tasse en plastique": {
                "col": "plastic_cup_tops"
            },
            "Bouteille/Brique de lait (plastique)": {
                "col": "milk_bottle"
            },
            "Bouteille/Brique de lait (carton)": {
                "col": "milk_carton"
            },
            "Verre en carton": {
                "col": "paper_cups"
            },
            "Brique de jus (carton)": {
                "col": "juice_cartons"
            },
            "Bouteille de jus": {
                "col": "juice_bottles"
            },
            "Emballage de jus": {
                "col": "juice_packet"
            },
            "Bouteille d`Ice Tea": {
                "col": "ice_tea_bottles"
            },
            "Cannette d`Ice Tea": {
                "col": "ice_tea_can"
            },
            "Cannette de boisson énergisante": {
                "col": "energy_can"
            },
            "Tasse de Polystyrène": {
                "col": "styro_cup"
            },
            "Boissons (autre)": {
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
            "Préservatif": {
                "col": "condoms"
            },
            "Couche pour bébé": {
                "col": "nappies"
            },
            "Serviettes hygiéniques": {
                "col": "menstral"
            },
            "Déodorant": {
                "col": "deodorant"
            },
            "Coton-tige": {
                "col": "ear_swabs"
            },
            "Cure-dent": {
                "col": "tooth_pick"
            },
            "Brosse à dents": {
                "col": "tooth_brush"
            },
            "Hygiène (autre)": {
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
            "Crotte de chien": {
                "col": "dogshit"
            },
            "Plastique non identifiable": {
                "col": "plastic"
            },
            "Décharge illégale": {
                "col": "dump"
            },
            "Objet métallique": {
                "col": "metal"
            },
            "Sac en plastique": {
                "col": "plastic_bags"
            },
            "Affiches élections": {
                "col": "election_posters"
            },
            "Affiche a vendre": {
                "col": "forsale_posters"
            },
            "Livre": {
                "col": "books"
            },
            "Magazine": {
                "col": "magazine"
            },
            "Papier": {
                "col": "paper"
            },
            "Papier à lettres": {
                "col": "stationary"
            },
            "Bouteille de détergent": {
                "col": "washing_up"
            },
            "Elastique à cheveux": {
                "col": "hair_tie"
            },
            "Ecouteurs": {
                "col": "ear_plugs"
            },
            "Autre (autre)": {
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
            "Microplastiques": {
                "col": "microplastics"
            },
            "Plastique (moyen)": {
                "col": "mediumplastics"
            },
            "Plastique (grand)": {
                "col": "macroplastics"
            },
            "Ficelle": {
                "col": "rope_small"
            },
            "Corde": {
                "col": "rope_medium"
            },
            "Cordage": {
                "col": "rope_large"
            },
            "Filet de pêche": {
                "col": "fishing_gear_nets"
            },
            "Bouée": {
                "col": "buoys"
            },
            "Bouteille en plastique (dégradée)": {
                "col": "degraded_plasticbottle"
            },
            "Sac en plastique (dégradé)": {
                "col": "degraded_plasticbag"
            },
            "Paille (dégradée)": {
                "col": "degraded_straws"
            },
            "Briquet (dégradé)": {
                "col": "degraded_lighters"
            },
            "Ballon": {
                "col": "balloons"
            },
            "Lego": {
                "col": "lego"
            },
            "Balle de fusil, cartouche": {
                "col": "shotgun_cartridges"
            },
            "Polystyrène small": {
                "col": "styro_small"
            },
            "Polystyrène medium": {
                "col": "styro_medium"
            },
            "Polystyrène large": {
                "col": "styro_large"
            },
            "Côtier (autre)": {
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

    private $jsonDecoded  = null;
    public static function INSTANCE(){
        static $inst = null;
        if ($inst === null) $inst = new LitterFrench();
        return $inst;
    }

    public function __construct(){
        $this->jsonDecoded = json_decode($this->json);
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

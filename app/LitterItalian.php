<?php

namespace App;

final class LitterItalian {
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Sigarette/Mozziconi": {
                "col": "butts"
            },
            "Accendini": {
                "col": "lighters"
            },
            "Scatole di sigarette": {
                "col": "cigaretteBox"
            },
            "Buste per il tabacco": {
                "col": "tobaccoPouch"
            },
            "Cartine": {
                "col": "skins"
            },
            "Imballaggi in plastica": {
                "col": "smoking_plastic"
            },
            "Filtri": {
                "col": "filters"
            },
            "Scatole dei filtri": {
                "col": "filterbox"
            },
            "Fumo (altro)": {
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
            "Lattine di birra": {
                "col": "beerCan"
            },
            "Bottiglie di vetro (birra)": {
                "col": "beerBottle"
            },
            "Bottiglie di vetro (superalcolici)": {
                "col": "spiritBottle"
            },
            "Bottiglie di vetro (vino)": {
                "col": "wineBottle"
            },
            "Vetro rotto": {
                "col": "brokenGlass"
            },
            "Tappi di bottiglie di birra": {
                "col": "bottleTops"
            },
            "Confezioni di carta": {
                "col": "paperCardAlcoholPackaging"
            },
            "Confezioni di plastica": {
                "col": "plasticAlcoholPackaging"
            },
            "Pinte di vetro": {
                "col": "pint"
            },
            "Alcolici (altro)": {
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
            "Tazza di caffe": {
                "col": "coffeeCups"
            },
            "Coperchi di caffe": {
                "col": "coffeeLids"
            },
            "Caffe (altro)": {
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
            "Involucri di dolci": {
                "col": "sweetWrappers"
            },
            "Confezioni di carta/cartone": {
                "col": "paperFoodPackaging"
            },
            "Confezioni di plastica": {
                "col": "plasticFoodPackaging"
            },
            "Posate di plastica": {
                "col": "plasticCutlery"
            },
            "Pacchetti di patatine (piccoli)": {
                "col": "crisp_small"
            },
            "Pacchetti di patatine (grandi)": {
                "col": "crisp_large"
            },
            "Polistirolo": {
                "col": "styrofoam_plate"
            },
            "Tovaglioli": {
                "col": "napkins"
            },
            "Bustine di salse": {
                "col": "sauce_packet"
            },
            "Vasetti di vetro": {
                "col": "glass_jar"
            },
            "Coperchi per vasetti di vetro": {
                "col": "glass_jar_lid"
            },
            "Cibo (altro)": {
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
            "Bottiglie d’acqua plastica": {
                "col": "waterBottle"
            },
            "Bottiglie bevande gassate": {
                "col": "fizzyDrinkBottle"
            },
            "Lattine": {
                "col": "tinCan"
            },
            "Linguette delle lattine": {
                "col": "pullring"
            },
            "Tappi di bottiglie": {
                "col": "bottleLid"
            },
            "Etichette di bottiglie": {
                "col": "bottleLabel"
            },
            "Bottiglie energetiche": {
                "col": "sportsDrink"
            },
            "Cannucce": {
                "col": "straws"
            },
            "Confezioni di cannucce": {
                "col": "strawpacket"
            },
            "Bicchieri di plastica": {
                "col": "plastic_cups"
            },
            "Coperchi per bicchieri di plastica": {
                "col": "plastic_cup_tops"
            },
            "Bottiglie di latte": {
                "col": "milk_bottle"
            },
            "Tetrapak di latte": {
                "col": "milk_carton"
            },
            "Bicchieri di carta": {
                "col": "paper_cups"
            },
            "Tetrapak di succhi": {
                "col": "juice_cartons"
            },
            "Bottiglie di succhi": {
                "col": "juice_bottles"
            },
            "Pacchetti di succo": {
                "col": "juice_packet"
            },
            "Bottiglie di tè freddo": {
                "col": "ice_tea_bottles"
            },
            "Lattine di tè freddo": {
                "col": "ice_tea_can"
            },
            "Lattine di bevande energetiche": {
                "col": "energy_can"
            },
            "Bicchieri di polistirolo": {
                "col": "styro_cup"
            },
            "Bibite analcoliche (altro)": {
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
            "Preservativi": {
                "col": "condoms"
            },
            "Pannolini": {
                "col": "nappies"
            },
            "Assorbenti": {
                "col": "menstral"
            },
            "Deodoranti": {
                "col": "deodorant"
            },
            "Cotton fioc": {
                "col": "ear_swabs"
            },
            "Stuzzicadenti": {
                "col": "tooth_pick"
            },
            "Spazzolini": {
                "col": "tooth_brush"
            },
            "Salviette umidificate": {
                "col": "wetwipes"
            },
            "Igiene (altro)": {
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
            "Discarica illegale": {
                "col": "dump"
            },
            "Cacca di cane": {
                "col": "dogshit"
            },
            "Cacca di cane in busta": {
                "col": "pooinbag"
            },
            "Plastica non identificata": {
                "col": "plastic"
            },
            "Salvagenti": {
                "col": "life_buoy"
            },
            "Coni stradali": {
                "col": "traffic_cone"
            },
            "Parti di automobili": {
                "col": "automobile"
            },
            "Palloncini": {
                "col": "balloons"
            },
            "Pile": {
                "col": "batteries"
            },
            "Vestiario": {
                "col": "clothing"
            },
            "Rottami elettronici (piccoli)": {
                "col": "elec_small"
            },
            "Rottami elettronici (grandi)": {
                "col": "elec_large"
            },
            "Oggetti di metallo": {
                "col": "metal"
            },
            "Buste di plastica": {
                "col": "plastic_bags"
            },
            "Manifesti elettorali": {
                "col": "election_posters"
            },
            "Manifesti di vendita": {
                "col": "forsale_posters"
            },
            "Libri": {
                "col": "books"
            },
            "Riviste": {
                "col": "magazine"
            },
            "Carta": {
                "col": "paper"
            },
            "Articoli di cancelleria": {
                "col": "stationary"
            },
            "Bottiglie di detersivi": {
                "col": "washing_up"
            },
            "Elastici per capelli": {
                "col": "hair_tie"
            },
            "Auricolari": {
                "col": "ear_plugs"
            },
            "Altro (altro)": {
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
            "Microplastiche": {
                "col": "microplastics"
            },
            "Plastica di medie dimensioni": {
                "col": "mediumplastics"
            },
            "Macroplastica": {
                "col": "macroplastics"
            },
            "Corde (piccole)": {
                "col": "rope_small"
            },
            "Corde (medie)": {
                "col": "rope_medium"
            },
            "Corde (grande)": {
                "col": "rope_large"
            },
            "Attrezzi da pesca/reti": {
                "col": "fishing_gear_nets"
            },
            "Boe": {
                "col": "buoys"
            },
            "Bottiglie di plastica degradate": {
                "col": "degraded_plasticbottle"
            },
            "Sacchetti di plastica degradati": {
                "col": "degraded_plasticbag"
            },
            "Cannucce degradate": {
                "col": "degraded_straws"
            },
            "Accendini degradati": {
                "col": "degraded_lighters"
            },
            "Palloni": {
                "col": "balloons"
            },
            "Lego": {
                "col": "lego"
            },
            "Cartucce per fucili": {
                "col": "shotgun_cartridges"
            },
            "Polistirolo (piccolo)": {
                "col": "styro_small"
            },
            "Polistirolo (medio)": {
                "col": "styro_medium"
            },
            "Polistirolo (grande)": {
                "col": "styro_large"
            },
            "Zone costiere (altro)": {
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
            $inst = new LitterItalian();
        }

        return $inst;
    }

    public function __construct(){
        $this->jsonDecoded = json_decode((string) $this->json);
    }

    public function getDecodedJSON(){ return $this->jsonDecoded; }
}

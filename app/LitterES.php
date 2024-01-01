<?php

namespace App;

final class LitterES{
    private $json = '
{
    "Smoking": {
        "table": "smoking",
        "id": "smoking_id",
        "class": "Smoking",
        "total": "total_smoking",
        "types": {
            "Colillas de cigarro": {
                "col": "butts"
            },
            "Encendedores": {
                "col": "lighters"
            },
            "Caja de cigarrillos": {
                "col": "cigaretteBox"
            },
            "Bolsa de tabaco": {
                "col": "tobaccoPouch"
            },
            "Papel de fumar": {
                "col": "skins"
            },
            "Embalaje de plástico": {
                "col": "smoking_plastic"
            },
            "Filtros": {
                "col": "filters"
            },
            "Caja de filtro": {
                "col": "filterbox"
            },
            "Fumar (otros)": {
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
            "Botellas de cerveza": {
                "col": "beerBottle"
            },
            "Botellas de licores": {
                "col": "spiritBottle"
            },
            "Botellas de vino": {
                "col": "wineBottle"
            },
            "Latas de cerveza": {
                "col": "beerCan"
            },
            "Vidrio roto": {
                "col": "brokenGlass"
            },
            "Tapas de botellas de cerveza": {
                "col": "bottleTops"
            },
            "Embalajes de papel": {
                "col": "paperCardAlcoholPackaging"
            },
            "Embalaje de plástico": {
                "col": "plasticAlcoholPackaging"
            },
            "Alcohol (otro)": {
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
            "Taza de café": {
                "col": "coffeeCups"
            },
            "Tapas de café": {
                "col": "coffeeLids"
            },
            "Café (otro)": {
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
            "Envolturas dulces": {
                "col": "sweetWrappers"
            },
            "Empaquetado de papel / cartón": {
                "col": "paperFoodPackaging"
            },
            "Embalaje de plástico": {
                "col": "plasticFoodPackaging"
            },
            "Cubiertos de plástico": {
                "col": "plasticCutlery"
            },
            "Paquete Crisp / Chip (pequeño)": {
                "col": "crisp_small"
            },
            "Paquete Crisp / Chip (grande)": {
                "col": "crisp_large"
            },
            "Poliestireno": {
                "col": "styrofoam_plate"
            },
            "Servilletas": {
                "col": "napkins"
            },
            "Paquete de salsa": {
                "col": "sauce_packet"
            },
            "Jarra de vidrio": {
                "col": "glass_jar"
            },
            "Tapa de jarra de vidrio": {
                "col": "glass_jar_lid"
            },
            "Comida (otros)": {
                "col": "foodOther"
            }
        }
    },
    "Soft-Drinks": {
        "id": "softdrinks_id",
        "table": "soft_drinks",
        "class": "SoftDrinks",
        "total": "total_softdrinks",
        "types": {
            "Botella de agua plástica": {
                "col": "waterBottle"
            },
            "Botella plástica de bebida gaseosa": {
                "col": "fizzyDrinkBottle"
            },
            "Lata (bebida gaseosa)": {
                "col": "tinCan"
            },
            "Tapas de botellas": {
                "col": "bottleLid"
            },
            "Etiquetas de botellas": {
                "col": "bottleLabel"
            },
            "Botella de bebida deportiva": {
                "col": "sportsDrink"
            },
            "Pajitas": {
                "col": "straws"
            },
            "Copas de plástico": {
                "col": "plastic_cups"
            },
            "Tapas de plástico": {
                "col": "plastic_cup_tops"
            },
            "Botella de leche": {
                "col": "milk_bottle"
            },
            "Cartón de leche": {
                "col": "milk_carton"
            },
            "Copas de papel": {
                "col": "paper_cups"
            },
            "Cajas de jugo": {
                "col": "juice_cartons"
            },
            "Botellas de jugo": {
                "col": "juice_bottles"
            },
            "Paquete de jugo": {
                "col": "juice_packet"
            },
            "Botellas de té": {
                "col": "ice_tea_bottles"
            },
            "Lata de Té": {
                "col": "ice_tea_can"
            },
            "Lata de bebida de energía": {
                "col": "energy_can"
            },
            "Cupas de Poliestireno": {
                "col": "styro_cup"
            },
            "Refresco (otro)": {
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
            "Condones": {
                "col": "condoms"
            },
            "Pañales": {
                "col": "nappies"
            },
            "Menstrual": {
                "col": "menstral"
            },
            "Desodorante": {
                "col": "deodorant"
            },
            "Hisopos de oído": {
                "col": "ear_swabs"
            },
            "Palillo de dientes": {
                "col": "tooth_pick"
            },
            "Cepillo de dientes": {
                "col": "tooth_brush"
            },
            "Sanitario (otros)": {
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
            "Basurero Grande / Variado": {
                "col": "dump"
            },
            "Mierda de perro": {
                "col": "dogshit"
            },
            "Plástico no identificado": {
                "col": "plastic"
            },
            "Objeto de metal": {
                "col": "metal"
            },
            "Bolsas de plástico": {
                "col": "plastic_bags"
            },
            "Carteles electorales": {
                "col": "election_posters"
            },
            "Carteles de Venta": {
                "col": "forsale_posters"
            },
            "Libros": {
                "col": "books"
            },
            "Revistas": {
                "col": "magazine"
            },
            "Papel": {
                "col": "paper"
            },
            "Estacionario": {
                "col": "stationary"
            },
            "Botella de lavado": {
                "col": "washing_up"
            },
            "Liga para el cabello": {
                "col": "hair_tie"
            },
            "Ear Plugs (música)": {
                "col": "ear_plugs"
            },
            "Otro (otro)": {
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
            "Microplasticos": {
                "col": "microplastics"
            },
            "Plásticos medianos": {
                "col": "mediumplastics"
            },
            "Macro plásticos": {
                "col": "macroplastics"
            },
            "Cuerda/soga pequeña": {
                "col": "rope_small"
            },
            "Cuerda/soga mediana": {
                "col": "rope_medium"
            },
            "Cuerda/soga grande": {
                "col": "rope_large"
            },
            "Articulos de pesca": {
                "col": "fishing_gear_nets"
            },
            "Boyas": {
                "col": "buoys"
            },
            "Botella de plástico degradado": {
                "col": "degraded_plasticbottle"
            },
            "Bolsa de plástico degradado": {
                "col": "degraded_plasticbag"
            },
            "Pajitas de beber degradadas": {
                "col": "degraded_straws"
            },
            "Encendedores degradados": {
                "col": "degraded_lighters"
            },
            "Globos": {
                "col": "balloons"
            },
            "Lego": {
                "total": "totalLego",
                "col": "lego"
            },
            "Cartuchos de escopeta": {
                "col": "shotgun_cartridges"
            },
            "Poliestireno pequeña": {
                "col": "styro_small"
            },
            "Poliestireno mediana": {
                "col": "styro_medium"
            },
            "Poliestireno grande": {
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
            "PerroYBasura": {
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
            $inst = new LitterES();
        }

        return $inst;
    }

    public function __construct(){
        $this->jsonDecoded = json_decode((string) $this->json);

    }

    public function getDecodedJSON(){ return $this->jsonDecoded; }
}

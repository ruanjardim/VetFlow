<?php

$lines = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $line): string => trim($line),
    preg_split('/\R/u', trim($value)) ?: []
)));

return [
    'Canino' => [
        'source' => 'FCI/CBKC',
        'breeds' => $lines(<<<'BREEDS'
Abruzzes And Maremma Shepherd Dog
Affenpinscher
Afghan Hound
Airedale Terrier
Akita
Alaskan Malamute
Alpine Dachsbracke
American Akita
American Cocker Spaniel
American Foxhound
American Staffordshire Terrier
American Water Spaniel
Appenzell Cattle Dog
Ariege Pointing Dog
Ariegeois
Artois Hound
Atlas Mountain Dog (Aidi)
Australian Cattle Dog
Australian Kelpie
Australian Shepherd
Australian Silky Terrier
Australian Terrier
Austrian Pinscher
Austrian Black And Tan Hound
Auvergne Pointer
Azawakh
Basenji
Basset Fauve De Bretagne
Basset Hound
Bavarian Mountain Scent Hound
Beagle
Beagle Harrier
Bearded Collie
Beauce Sheepdog
Bedlington Terrier
Belgian Shepherd Dog
Bergamasco Shepherd Dog
Bernese Mountain Dog
Bichon Frise
Billy
Black And Tan Coonhound
Bloodhound
Blue Gascony Basset
Blue Gascony Griffon
Blue Picardy Spaniel
Bohemian Wire-Haired Pointing Griffon
Bolognese
Border Collie
Border Terrier
Borzoi - Russian Hunting Sighthound
Bosnian And Herzegovinian - Croatian Shepherd Dog
Bosnian Broken-Haired Hound - Called Barak
Boston Terrier
Bourbonnais Pointing Dog
Bouvier Des Ardennes
Bouvier Des Flandres
Boxer
Brazilian Terrier
Brazilian Tracker
Briard
Briquet Griffon Vendeen
Brittany Spaniel
Broholmer
Bull Terrier
Bulldog
Bullmastiff
Burgos Pointing Dog
Cairn Terrier
Canaan Dog
Canadian Eskimo Dog
Canarian Warren Hound
Castro Laboreiro Dog
Catalan Sheepdog
Caucasian Shepherd Dog
Cavalier King Charles Spaniel
Central Asia Shepherd Dog
Chesapeake Bay Retriever
Chihuahua
Chinese Crested Dog
Chow Chow
Cimarrón Uruguayo
Cirneco Dell'Etna
Clumber Spaniel
Coarse-Haired Styrian Hound
Collie Rough
Collie Smooth
Continental Toy Spaniel
Coton De Tulear
Croatian Shepherd Dog
Curly Coated Retriever
Czech Terrier
Czechoslovakian Wolfdog
Dachshund
Dalmatian
Dandie Dinmont Terrier
Danish-Swedish Farmdog
Deerhound
Deutsch Langhaar
Deutsch Stichelhaar
Dobermann
Dogo Argentino
Dogue De Bordeaux
Drentsche Partridge Dog
Drever
Dutch Schapendoes
Dutch Shepherd Dog
Dutch Smoushond
East Siberian Laika
English Cocker Spaniel
English Foxhound
English Pointer
English Setter
English Springer Spaniel
English Toy Terrier (Black & Tan)
Entlebuch Cattle Dog
Estrela Mountain Dog
Eurasian
Fawn Brittany Griffon
Field Spaniel
Fila Brasileiro
Finnish Hound
Finnish Lapponian Dog
Finnish Spitz
Flat Coated Retriever
Fox Terrier (Smooth)
Fox Terrier (Wire)
French Bulldog
French Pointing Dog - Gascogne Type
French Pointing Dog - Pyrenean Type
French Spaniel
French Tricolour Hound
French Water Dog
French White & Black Hound
French White And Orange Hound
Gascon Saintongeois
German Hound
German Hunting Terrier
German Pinscher
German Shepherd Dog
German Short-Haired Pointing Dog
German Spaniel
German Spitz
German Wire-Haired Pointing Dog
Giant Schnauzer
Golden Retriever
Gordon Setter
Grand Basset Griffon Vendeen
Grand Griffon Vendeen
Great Anglo-French Tricolour Hound
Great Anglo-French White & Orange Hound
Great Anglo-French White And Black Hound
Great Dane
Great Gascony Blue
Great Swiss Mountain Dog
Greenland Dog
Greyhound
Griffon Belge
Griffon Bruxellois
Griffon Nivernais
Halden Hound
Hamiltonstövare
Hanoverian Scent Hound
Harrier
Havanese
Hellenic Hound
Hokkaido
Hovawart
Hungarian Greyhound
Hungarian Hound - Transylvanian Scent Hound
Hungarian Short-Haired Pointer (Vizsla)
Hungarian Wire-Haired Pointer
Hygen Hound
Ibizan Podenco
Icelandic Sheepdog
Irish Glen Of Imaal Terrier
Irish Red And White Setter
Irish Red Setter
Irish Soft Coated Wheaten Terrier
Irish Terrier
Irish Water Spaniel
Irish Wolfhound
Istrian Short-Haired Hound
Istrian Wire-Haired Hound
Italian Cane Corso
Italian Pointing Dog
Italian Rough-Haired Segugio
Italian Short-Haired Segugio
Italian Sighthound
Italian Spinone
Italian Volpino
Jack Russell Terrier
Jämthund
Japanese Chin
Japanese Spitz
Japanese Terrier
Kai
Kangal Shepherd Dog
Karelian Bear Dog
Karst Shepherd Dog
Kerry Blue Terrier
King Charles Spaniel
Kishu
Kleiner Münsterländer
Komondor
Korea Jindo Dog
Kromfohrländer
Kuvasz
Labrador Retriever
Lakeland Terrier
Landseer (European Continental Type)
Lapponian Herder
Large Munsterlander
Leonberger
Lhasa Apso
Little Lion Dog
Long-Haired Pyrenean Sheepdog
Majorca Mastiff
Majorca Shepherd Dog
Maltese
Manchester Terrier
Mastiff
Medium-Sized Anglo-French Hound
Miniature Bull Terrier
Miniature Pinscher
Miniature Schnauzer
Montenegrin Mountain Hound
Mudi
Neapolitan Mastiff
Nederlandse Kooikerhondje
Newfoundland
Norfolk Terrier
Norman Artesien Basset
Norrbottenspitz
Norwegian Buhund
Norwegian Elkhound Black
Norwegian Elkhound Grey
Norwegian Hound
Norwegian Lundehund
Norwich Terrier
Nova Scotia Duck Tolling Retriever
Old Danish Pointing Dog
Old English Sheepdog
Otterhound
Parson Russell Terrier
Pekingese
Peruvian Hairless Dog
Petit Basset Griffon Vendeen
Petit Brabançon
Pharaoh Hound
Picardy Sheepdog
Picardy Spaniel
Poitevin
Polish Greyhound
Polish Hound
Polish Hunting Dog
Polish Lowland Sheepdog
Polish Tatra Shepherd
Pont-Audemer Spaniel
Poodle
Porcelaine
Portuguese Pointing Dog
Portuguese Sheepdog
Portuguese Warren Hound-Portuguese Podengo
Portuguese Water Dog
Posavatz Hound
Presa Canario
Pudelpointer
Pug
Puli
Pumi
Pyrenean Mastiff
Pyrenean Mountain Dog
Pyrenean Sheepdog - Smooth Faced
Rafeiro Of Alentejo
Rhodesian Ridgeback
Romagna Water Dog
Romanian Bucovina Shepherd
Romanian Carpathian Shepherd Dog
Romanian Mioritic Shepherd Dog
Rottweiler
Russian Black Terrier
Russian Toy
Russian-European Laika
Saarloos Wolfhond
Saint Germain Pointer
Saint Miguel Cattle Dog
Saluki
Samoyed
Schillerstövare
Schipperke
Schnauzer
Scottish Terrier
Sealyham Terrier
Serbian Hound
Serbian Tricolour Hound
Shar Pei
Shetland Sheepdog
Shiba
Shih Tzu
Shikoku
Siberian Husky
Skye Terrier
Sloughi
Slovakian Chuvach
Slovakian Hound
Smålandsstövare
Small Blue Gascony
Small Swiss Hound
South Russian Shepherd Dog
Spanish Greyhound
Spanish Hound
Spanish Mastiff
Spanish Water Dog
St. Bernard
Stabijhoun
Staffordshire Bull Terrier
Sussex Spaniel
Swedish Lapphund
Swedish Vallhund
Swiss Hound
Taiwan Dog
Thai Bangkaew Dog
Thai Ridgeback Dog
Tibetan Mastiff
Tibetan Spaniel
Tibetan Terrier
Tosa
Tyrolean Hound
Weimaraner
Welsh Corgi (Cardigan)
Welsh Corgi (Pembroke)
Welsh Springer Spaniel
Welsh Terrier
West Highland White Terrier
West Siberian Laika
Westphalian Dachsbracke
Wetterhoun
Whippet
White Swiss Shepherd Dog
Wire-Haired Pointing Griffon Korthals
Wirehaired Slovakian Pointer
Xoloitzcuintle
Yorkshire Terrier
Yugoslavian Shepherd Dog - Sharplanina
Australian Stumpy Tail Cattle Dog
Chodsky Pes (Bohemian Shepherd Dog)
Ciobănesc Românesc Corb (Romanian Raven Shepherd Dog)
Continental Bulldog
Cão de Gado Transmontano
Buldogue Campeiro
Gos Rater Valencià
Kintamani-Bali Dog
Kazakh Tazy
Lancashire Heeler
Macedonian Shepherd Dog Karaman
Miniature American Shepherd
Prazsky Krysarik (Prague Ratter)
Ratonero Bodeguero Andaluz
Sabueso Fino Colombiano
Segugio dell'Appennino
Segugio Maremmano
Tatranský Durič (Tatra Hound)
Yakutian Laika
Dogue Brasileiro
Galgo da Campanha
Ovelheiro Gaúcho
Veadeiro Pampeano
Buldogue Serrano
BREEDS),
    ],
    'Felino' => [
        'source' => 'TICA/FIFe',
        'breeds' => $lines(<<<'BREEDS'
Abyssinian
American Bobtail
American Bobtail Shorthair
American Curl
American Curl Longhair
American Shorthair
American Wirehair
Australian Mist
Balinese
Bengal
Bengal Longhair
Birman
Bombay
British Longhair
British Shorthair
Burmese
Burmilla
Burmilla Longhair
Chartreux
Chausie
Cherubim
Cornish Rex
Cymric
Cymric Tailed
Devon Rex
Donskoy
Egyptian Mau
Exotic Shorthair
German Rex
Havana
Highlander
Highlander Shorthair
Himalayan
Japanese Bobtail
Japanese Bobtail Longhair
Khao Manee
Korat
Kurilian Bobtail
Kurilian Bobtail Longhair
LaPerm
LaPerm Shorthair
Lykoi
Maine Coon
Maine Coon Polydactyl
Manx
Manx Tailed
Minuet
Minuet Longhair
Minuet Talls
Minuet Talls Longhair
Munchkin
Munchkin Longhair
Nebelung
Neva Masquerade
Norwegian Forest
Ocicat
Oriental Longhair
Oriental Shorthair
Persian
Peterbald
Pixiebob
Pixiebob Longhair
Ragdoll
Russian Blue
Savannah
Scottish Fold
Scottish Fold Longhair
Scottish Straight
Scottish Straight Longhair
Selkirk Rex
Selkirk Rex Longhair
Siamese
Siberian
Singapura
Snowshoe
Sokoke
Somali
Sphynx
Tennessee Rex
Thai
Tonkinese
Toyger
Turkish Angora
Turkish Van
Gato doméstico de pelo curto (SRD)
Gato doméstico de pelo longo (SRD)
BREEDS),
    ],
    'Serpente' => [
        'source' => 'Reptile Database/IBAMA',
        'breeds' => $lines(<<<'BREEDS'
Sem variedade definida
Jiboia (Boa constrictor)
Jiboia-arco-íris (Epicrates cenchria)
Jiboia-verde (Corallus caninus)
Suaçuboia (Corallus hortulanus)
Sucuri-verde (Eunectes murinus)
Sucuri-amarela (Eunectes notaeus)
Píton-real (Python regius)
Píton-birmanesa (Python bivittatus)
Píton-reticulada (Malayopython reticulatus)
Píton-tapete (Morelia spilota)
Píton-verde-arborícola (Morelia viridis)
Píton-sangue (Python brongersmai)
Píton-das-crianças (Antaresia childreni)
Píton-de-Woma (Aspidites ramsayi)
Cobra-do-milho (Pantherophis guttatus)
Cobra-rato-do-Texas (Pantherophis obsoletus)
Cobra-rato-russa (Elaphe schrenckii)
Falsa-coral-leiteira (Lampropeltis triangulum)
Kingsnake-da-Califórnia (Lampropeltis californiae)
Kingsnake-comum (Lampropeltis getula)
Cobra-nariz-de-porco (Heterodon nasicus)
Cobra-liga (Thamnophis sirtalis)
Cobra-touro (Pituophis catenifer sayi)
Cobra-de-água-brasileira (Helicops infrataeniatus)
Cobra-papagaio (Leptophis ahaetulla)
Cobra-cipó-verde (Chironius bicarinatus)
Caninana (Spilotes pullatus)
Muçurana (Boiruna maculata)
Falsa-coral-brasileira (Oxyrhopus guibei)
Dormideira (Dipsas mikanii)
Jararaca (Bothrops jararaca)
Jararacuçu (Bothrops jararacussu)
Jararaca-do-norte (Bothrops atrox)
Urutu-cruzeiro (Bothrops alternatus)
Cotiara (Bothrops cotiara)
Cascavel (Crotalus durissus)
Surucucu-pico-de-jaca (Lachesis muta)
Coral-verdadeira (Micrurus corallinus)
Coral-verdadeira-amazônica (Micrurus spixii)
Naja-indiana (Naja naja)
Naja-egípcia (Naja haje)
Naja-cuspideira-de-Moçambique (Naja mossambica)
Mamba-negra (Dendroaspis polylepis)
Mamba-verde-oriental (Dendroaspis angusticeps)
Taipan-costeiro (Oxyuranus scutellatus)
Cobra-marrom-oriental (Pseudonaja textilis)
Víbora-do-Gabão (Bitis gabonica)
Víbora-sopradora (Bitis arietans)
Víbora-de-chifres (Cerastes cerastes)
Víbora-de-Russell (Daboia russelii)
Boomslang (Dispholidus typus)
Krait-listrada (Bungarus fasciatus)
BREEDS),
    ],
    'Equino' => [
        'source' => 'MAPA/FAO',
        'breeds' => $lines(<<<'BREEDS'
Akhal-Teke
American Cream Draft
American Paint Horse
American Quarter Horse
American Saddlebred
Andaluz (Pura Raça Espanhola)
Anglo-Árabe
Appaloosa
Árabe
Ardennes
Australian Stock Horse
Belgian Draft
Brasileiro de Hipismo
Bretão
Budyonny
Campolina
Caspian
Cavalo Baixadeiro
Cavalo Lavradeiro
Clydesdale
Crioulo
Falabella
Fjord Norueguês
Friesian
Gypsy Cob
Gypsy Vanner
Haflinger
Hanoveriano
Holsteiner
Icelandic Horse
Knabstrupper
Lipizzaner
Lusitano
Mangalarga
Mangalarga Marchador
Marajoara
Morgan
Mustang
Nordestino
Oldenburg
Pantaneiro
Paso Fino
Percheron
Piquira
Pônei Brasileiro
Pônei Shetland
Puro Sangue Inglês
Quarto de Milha
Rocky Mountain Horse
Shire
Sorraia
Standardbred
Suffolk Punch
Tennessee Walking Horse
Trakehner
Westphalian
BREEDS),
    ],
    'Bovino' => [
        'source' => 'MAPA/FAO',
        'breeds' => $lines(<<<'BREEDS'
Aberdeen Angus
Afrikaner
Angus
Ayrshire
Belgian Blue
Blonde d'Aquitaine
Bonsmara
Boran
Braford
Brahman
Brangus
Brown Swiss (Pardo-Suíço)
Canchim
Caracu
Charolês
Chianina
Curraleiro Pé-Duro
Devon
Dexter
Fleckvieh
Franqueiro
Galloway
Gir
Girolando
Guzerá
Hereford
Highland Cattle
Holandês
Indubrasil
Jersey
Kangayam
Limousin
Marchigiana
Mocho Nacional
Montana Tropical
Murray Grey
Nelore
Nelore Mocho
Normando
Piemontês
Pinzgauer
Red Angus
Red Poll
Romagnola
Salers
Santa Gertrudis
Senepol
Shorthorn
Simental
Simbrasil
South Devon
Tabapuã
Texas Longhorn
Wagyu
White Park
Yacumento
BREEDS),
    ],
    'Suíno' => [
        'source' => 'MAPA/FAO',
        'breeds' => $lines(<<<'BREEDS'
Alentejana
Berkshire
Canastra
Canastrão
Caruncho
Casco de Burro
Chester White
Duroc
Gloucestershire Old Spots
Hampshire
Hereford
Ibérico
Landrace
Large Black
Large White
Mangalitsa
Meishan
Moura
Nilo
Piau
Pietrain
Poland China
Red Wattle
Saddleback
Sem raça definida
Sorocaba
Spot
Tamworth
Tatu
Vietnamita
Wessex Saddleback
Yorkshire
BREEDS),
    ],
];

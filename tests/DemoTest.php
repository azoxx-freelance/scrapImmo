<?php

namespace App\Tests;

use App\Entity\Annonce;
use App\Entity\Caracteristique;
use App\Entity\Photo;
use App\Service\ScrapAnnonceService;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client;

class DemoTest extends WebTestCase
{
    private $em;
    private $crawler;
    private $client;
    private $domDocument;
    private $debug = false;
    private $debugMethod = false;

    //public function testTrueIsTrue()
    public function testScrapAnnonceSeLoger()
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $annonceRepo = $this->em->getRepository(Annonce::class);
        $caracRepo = $this->em->getRepository(Caracteristique::class);
        $photoRepo = $this->em->getRepository(Photo::class);
        $hasResult = true;
        $page = 1;
        $startPage = $page;

        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        //////////////////////////////////////// SCRAP ALL NEW ANNONCES /////////////////////////////////////////
        /////////////////////////////////////////////////////////////////////////////////////////////////////////


        while($hasResult) {
            $this->initPantherClient($page, ($page == $startPage));
            $this->assertNoSymfonyException($this->client, '[data-testid="serp-core-scrollablelistview-testid"]');
            $xpath = $this->geXPath();
            $nodes = $xpath->query('//*'.'[@data-testid="card-mfe-covering-link-testid"]');
            dump('Page : ' . $page);
            dump('Nombre de blocs trouvés : ' . $nodes->length);

            $result = ['added' => [], 'exist' => []];

            foreach ($nodes as $node) {
                if ($node->nodeName !== 'a') {
                    continue;
                }

                $href = $node->getAttribute('href');
                $hrefClean = explode('?', $href)[0];

                if (preg_match('/\/([A-Za-z0-9]+)\.htm$/', $hrefClean, $matches)) {
                    $idAnnonce = $matches[1];

                    $existingById = $annonceRepo->findOneBy(['id' => $idAnnonce]);
                    $existingByUrl = $annonceRepo->findOneBy(['lien' => $hrefClean]);

                    if (!$existingById && !$existingByUrl) {
                        $annonce = new Annonce();
                        $annonce->setId($idAnnonce);
                        $annonce->setLien($hrefClean);
                        $annonce->setSource(Annonce::$sourceArray['SL']);
                        $this->em->persist($annonce);
                        $result['added'][] = $idAnnonce;
                    } else {
                        $result['exist'][] = $idAnnonce;
                    }
                } else {
                    dump("Impossible d’extraire l’ID pour : $hrefClean");
                }
            }

            dump("Ajouté: " . count($result['added']));
            dump("Existante: " . count($result['exist']));
            //dump($result);

            //if(count($result['added']) == 0 && count($result['exist']) == 0){
            if(count($result['added']) == 0){
                $hasResult = false;
            }

            $this->em->flush();
            $page++;
        }


        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        /////////////////////////////////////////// FILL NEW ANNONCES ///////////////////////////////////////////
        /////////////////////////////////////////////////////////////////////////////////////////////////////////

        $newAnnonces = $annonceRepo
            ->createQueryBuilder('a')
            ->leftJoin('a.caracteristiques', 'c')
            ->andWhere('c.id IS NULL')
            ->getQuery()
            ->getResult();

        //$newAnnonces = $annonceRepo->findBy(['status' => null]);
        dump("New Annonce: " . count($newAnnonces));
        $caracList = Caracteristique::$caracteristiquesCategories;

        /** @var Annonce $annonce */
        foreach ($newAnnonces as $annonce){
            dump((new \DateTime())->format('H:i:s')." - #" . $annonce->getId());
            $this->crawlNewUrl($annonce->getLien());
            $xpath = $this->geXPath();
            $this->client->getWebDriver()->executeScript("UC_UI.denyAllConsents().then(UC_UI.closeCMP);");
            //$outerHtml = $this->domDocument->saveHTML($nodeInfo[0]);

            /////////////// ADRESSE ///////////////
            $adresseRaw = $xpath->evaluate('string(//button[@data-testid="cdp-location-address"])');
            $adresse = trim(preg_replace('/\s+/u', ' ', html_entity_decode($adresseRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $annonce->setAdresse($adresse);


            /////////////// ANNONCEUR ///////////////
            $annonceurRaw = $xpath->evaluate('string(//div[@data-testid="aviv.CDP.Contacting.ContactCard.Title"])');
            $annonceur = trim(preg_replace('/\s+/u', ' ', html_entity_decode($annonceurRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $annonce->setAnnonceur($annonceur);


            /////////////// ANNONCEUR ///////////////
            $typeRaw = $xpath->evaluate('string(//span[@data-testid="cdp-hardfacts"])');
            $type = trim(preg_replace('/\s+/u', ' ', html_entity_decode($typeRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $annonce->setType($type);


            /////////////// MEDIAS ///////////////
            $mediaNodes = $xpath->evaluate('//section[@id="richMedia"]');
            foreach ($mediaNodes as $node) {
                $mediaRaw = $xpath->evaluate('string(.//h2)');
                $mediaTitle = trim(preg_replace('/\s+/u', ' ', html_entity_decode($mediaRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                $existCarac = $caracRepo->findOneBy(['code' => 'MEDIA', 'value' => $mediaTitle, 'annonce' => $annonce]);
                if (!$existCarac) {
                    /** @var Caracteristique $carac */
                    $carac = new Caracteristique();
                    $carac->setCode('MEDIA');
                    $carac->setValue($mediaTitle);
                    $carac->setAnnonce($annonce);
                    $this->em->persist($carac);
                }
            }


            /////////////// PRIX & SURFACE & ETAGE & NBPIECES ///////////////
            // "price" => 255000, "price_m2" => 4474, "details" => array:4 [0 => "3 pièces", 1 => "2 chambres", 2 => "57 m²", 3 => "Étage 2/4"]
            $nodeInfo = $xpath->query('//*'.'[@data-testid="cdp-seo-wrapper"]');
            $infos = $this->extractAnnonceInfos($xpath, $nodeInfo->item(0));
            $annonce->setPrix($infos['price']);
            $annonce->setPrixSurface($infos['price_m2']);
            foreach ($infos['details'] as $value) {
                $valueModified = str_replace('RDC', '0', $value);
                if(strpos($value, 'pièce')) {
                    $annonce->setNbPiece((int)$value);
                } else if(strpos($value, 'chambre')) {
                    $annonce->setNbChambre((int)$value);
                } else if(strpos($value, 'm²')) {
                    $annonce->setSurface((int)$value);
                } else if(strpos($valueModified, 'tage') || preg_match('/\b([ÉE]tage\s+)?(\d+)(?:\s*\/\s*(\d+))?$/iu', $valueModified)) {
                    if (preg_match('/\b([ÉE]tage\s+)?(\d+)(?:\s*\/\s*(\d+))?$/iu', $valueModified, $m)) {
                        $annonce->setEtage((int)$m[2]);
                        $annonce->setEtageMax((isset($m[3]) && $m[3] !== '')? (int)$m[3] : null);
                    }
                } else if(strpos($valueModified, 'ème étage')) {
                    $annonce->setEtage((int)$valueModified);
                } else {
                    $existCarac = $caracRepo->findOneBy(['code' => 'INFO', 'value' => $value, 'annonce' => $annonce]);
                    if (!$existCarac) {
                        /** @var Caracteristique $carac */
                        $carac = new Caracteristique();
                        $carac->setCode('INFO');
                        $carac->setValue($value);
                        $carac->setAnnonce($annonce);
                        $this->em->persist($carac);
                    }
                }
            }


            $nodes = $xpath->query('//div[contains(normalize-space(.), "Les honoraires sont à la charge du vendeur")]');
            if(count($nodes) > 0) {
                $annonce->setNotaire(false);
            } else {
                $annonce->setNotaire(true);
            }


            /////////////// TITRE & DESCRIPTION ///////////////
            $titleRaw = $xpath->evaluate('string(//h2[@id="description"])');
            $title = trim(preg_replace('/\s+/u', ' ', html_entity_decode($titleRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $annonce->setTitre($title);

//            $descRaw = $xpath->evaluate('string(//div[contains(concat(" ", normalize-space(@class), " "), " DescriptionTexts ")])');
//            $desc = trim(preg_replace('/\s+/u', ' ', html_entity_decode($descRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
//            $annonce->setDescription($desc);

            $descNodeList = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " DescriptionTexts ")]');
            if ($descNodeList->length > 0) {
                $descNode = $descNodeList->item(0);
                $desc = $descNode->textContent;
                $annonce->setDescription($desc);
            }


            /////////////// DPE & GES ///////////////
            $energyNodes = $xpath->query('//section[@data-testid="cdp-energy"]//div[@data-testid="cdp-preview-scale-highlighted"]');
            $values = [];

            foreach ($energyNodes as $node) {
                $value = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? ''));
                if ($value !== '') { $values[] = $value; }
            }
            $annonce->setDpe((array_key_exists(0, $values))?$values[0]:null);
            $annonce->setGes((array_key_exists(1, $values))?$values[1]:null);


            $list = $xpath->query( '//ul[@data-testid="cdp-energy-features"]//li//div');

            foreach ($list as $item) {
                $keyValueNodes = $xpath->query( './/span', $item);
                $keyItem = $keyValueNodes->item(0)->textContent;
                $valItem = $keyValueNodes->item(1)->textContent;

                if ($keyItem !== '' && $valItem !== '') {
                    $isAutre = true;

                    foreach ($caracList as $key => $caracItem) {
                        if(strpos(strtolower($keyItem), $key) || strtolower($keyItem) == $key){
                            if(method_exists($annonce, $caracItem)){
                                $annonce->$caracItem($valItem);
                            } else {
                                $existCarac = $caracRepo->findOneBy(['code' => $caracItem, 'value' => $valItem, 'annonce' => $annonce]);
                                if (!$existCarac) {
                                    /** @var Caracteristique $carac */
                                    $carac = new Caracteristique();
                                    $carac->setCode($caracItem);
                                    $carac->setValue($valItem);
                                    $carac->setAnnonce($annonce);
                                    $this->em->persist($carac);
                                }
                            }
                            $isAutre = false;
                            break;
                        }
                    }

                    if($isAutre) {
                        $existCarac = $caracRepo->findOneBy(['value'=>$valItem, 'annonce'=>$annonce]);
                        if(!$existCarac) {
                            $carac = new Caracteristique();
                            $carac->setCode('AUTRE');
                            $carac->setValue($keyItem.': '.$valItem);
                            $carac->setAnnonce($annonce);
                            $this->em->persist($carac);
                        }
                    }
                }
            }

            /////////////// CHARGES COPRO ///////////////
            $textSearched = "Charges de copropriété";
            $nodes = $xpath->query('//div[normalize-space(text())="'.$textSearched.'"]/ancestor::*[5]');

            foreach ($nodes as $node) {
                $value = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? ''));
                $annonce->setChargeCopro((int)str_replace($textSearched, '', $value));
            }

            /////////////// IMAGES ///////////////
            $nbImgNodes = count($xpath->query('//div[@aria-roledescription="carousel"]//div[@aria-roledescription="slide"]'));

            for($i = 0; $i < $nbImgNodes-2; $i++)
                $this->clickOnElem($this->client, '[aria-label="aller à la slide suivante"]');

            $this->clickOnElem($this->client, 'section[data-testid="cdp-features"] button', null, 3);

            $this->crawler = $this->client->refreshCrawler();
            $xpath = $this->geXPath();
            $nodes = $xpath->query('//div[@aria-roledescription="carousel"]//img');
            $nodesPlan = $xpath->query('//div[@data-testid="cdp-floorplan-preview-container"]//img');
            $nodes = array_merge(iterator_to_array($nodes), iterator_to_array($nodesPlan));

            foreach ($nodes as $imgNode) {
                $src = $imgNode->getAttribute('src');
                if ($src) {
                    $existPhoto = $photoRepo->findOneBy(['url'=>$src]);
                    if(!$existPhoto) {
                        /** @var Photo $photo */
                        $photo = new Photo();
                        $photo->setAnnonce($annonce);
                        $photo->setUrl($src);
                        $this->em->persist($photo);
                    }
                }
            }


            /////////////// CARACTERISTIQUES ///////////////
            $listResume = $xpath->query( '//section[@data-testid="cdp-features"]//ul//li');
            $listComplete = $xpath->query( '//div[@role="dialog"]//li');
            $list = array_merge(iterator_to_array($listResume), iterator_to_array($listComplete));

            foreach ($list as $item) {
                // chaînes, ex: ["Ascenseur", "Balcon", "Cave", ...]
                $text = strtolower($xpath->evaluate('normalize-space(string(.))', $item));
                if ($text !== '') {
                    $isAutre = true;

                    foreach ($caracList as $key => $caracItem) {
                        if(strpos($text, $key) || $text == $key){
                            $existCarac = $caracRepo->findOneBy(['code'=>$caracItem, 'value'=>$text, 'annonce'=>$annonce]);
                            if(!$existCarac) {
                                /** @var Caracteristique $carac */
                                $carac = new Caracteristique();
                                $carac->setCode($caracItem);
                                $carac->setValue($text);
                                $carac->setAnnonce($annonce);
                                $this->em->persist($carac);
                            }
                            $isAutre = false;
                            break;
                        }
                    }

                    if($isAutre) {
                        $existCarac = $caracRepo->findOneBy(['value'=>$text, 'annonce'=>$annonce]);
                        if(!$existCarac) {
                            $carac = new Caracteristique();
                            $carac->setCode('AUTRE');
                            $carac->setValue($text);
                            $carac->setAnnonce($annonce);
                            $this->em->persist($carac);
                        }
                    }
                }
            }

            $annonce->setStatus('SCRAPED');
            $annonce->isActive(true);
            $this->em->flush();
        }

        dump('flush');
        $this->em->flush();
        dump('END');

        sleep(20);
    }









    function extractAnnonceInfos(\DOMXPath $xpath, \DOMNode $blockNode): array
    {
        // normalise les espaces / NBSP / narrow no-break space
        $normalize = function (string $s): string {
            $s = html_entity_decode($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
            // remplace différents espaces non standard par un espace normal
            $s = preg_replace('/[\x{00A0}\x{202F}\x{2009}]/u', ' ', $s);
            $s = preg_replace('/\s+/u', ' ', $s);
            return trim($s);
        };

        $result = [
            'price' => null,
            'price_m2' => null,
            'details' => [], // ex: ['3 pièces', '2 chambres', '57 m²', 'Étage 2/4']
        ];

        // 1) Cherche tous les <span> dans le bloc — plus sûr que se baser sur des classes CSS fragiles
        $spans = $xpath->query('.//span', $blockNode);

        foreach ($spans as $span) {
            $text = $normalize($span->textContent);

            // prix absolu (ex: "255 000 €" ou "255\u{202F}000\u{A0}€" => 255000)
            if ($result['price'] === null && preg_match('/\d[\d\s\.]*\s*€/u', $text) && !preg_match('/m²/u', $text)) {
                // garde seulement les chiffres
                $digits = preg_replace('/[^\d]/', '', $text);
                if ($digits !== '') {
                    $result['price'] = (int) $digits;
                    continue;
                }
            }

            // prix au m² (ex: "4 474 €/m²" ou "4474 €/m²")
            if ($result['price_m2'] === null && preg_match('/([\d\s\.\x{202F}\x{00A0}]+)\s*(€\/m²|€\/m2|€\/m²)/iu', $text, $m)) {
                $digits = preg_replace('/[^\d]/', '', $m[1]);
                if ($digits !== '') {
                    $result['price_m2'] = (int) $digits;
                    continue;
                }
            }
        }


        $groupNodes = $xpath->query('.//div[contains(@class,"css-7tj8u")]//span', $blockNode);

        if ($groupNodes->length > 0) {
            foreach ($groupNodes as $g) {
                $txt = trim(str_replace(['•', "\u{A0}"], '', $g->textContent));
                if ($txt !== '' && strlen($txt) > 2) {
                    $result['details'][] = $txt;
                }
            }
        }

        return $result;
    }


    /*
        <h1 class="css-qk4947" data-testid="cdp-seo-wrapper">
            <span class="css-1b9ytm" data-testid="cdp-hardfacts">
                Appartement à vendre
            </span>
            <div class="css-1ez736g">
                <div class="css-k0qv01">
                    <span class="css-9wpf20" style="clip:rect(1px, 1px, 1px, 1px)">
                        255000 €
                    </span>
                    <span aria-hidden="true" class="css-otf0vo">
                        255\u{202F}000\u{A0}€
                    </span>
                    <div class="css-19wvzv">
                        <span>
                            4\u{202F}474\u{A0}€/m²
                        </span>
                    </div>
                </div>
                <span class="css-pzutvj">
                    <div class="css-1nepcw9"></div>
                </span>
                <div class="css-1o63uzj">
                    <div class="css-18gtetq">
                        <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-feayc8">
                            <path d="M16.8 2.4H7.2a2.4 2.4 0 0 0-2.4 2.4v14.4a2.4 2.4 0 0 0 2.4 2.4h9.6a2.4 2.4 0 0 0 2.4-2.4V4.8c0-1.325-1.076-2.4-2.4-2.4M18 19.2c0 .662-.538 1.2-1.2 1.2H7.2c-.662 0-1.2-.538-1.2-1.2V8.4h12zm0-12H6V4.8c0-.661.538-1.2 1.2-1.2h9.6c.662 0 1.2.539 1.2 1.2zM7.8 18.6h4.8a.6.6 0 1 0 0-1.2H7.8a.6.6 0 0 0 0 1.2m7.8.3a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8m0-3.6a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8m0-3.6a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8M12 15.3a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8m0-3.6a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8m-3.6 3.6a.9.9 0 1 0 0-1.8.9.9 0 0 0 01.8m0-3.6a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8">
                            </path>
                        </svg>
                        <a tabindex="0" data-react-aria-pressable="true" target="_self" aria-disabled="false" class="css-18eycei" href="#%C3%80%20partir%20de%201288%20%E2%82%AC/mois">À partir de 1288 €/mois</a>
                        <button tabindex="0" data-react-aria-pressable="true" class="css-1o7ia5z">
                            <div class="css-t2mtid">
                                <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-ps6fn6">
                                    <path d="M12 2.4A9.6 9.60 0 0 2.4 12a9.6 9.6 0 0 0 9.6 9.6 9.6 9.6 0 0 0 9.6-9.6A9.6 9.6 0 0 0 12 2.4m0 18c-4.631 0-8.4-3.769-8.4-8.4S7.369 3.6 12 3.6s8.4 3.769 8.4 8.4-3.769 8.4-8.4 8.4m0-11.1a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8m1.8 6.3h-1.2v-4.2c0-.33-.27-.6-.6-.6h-1.2c-.33 0-.6.27-.6.6s.27.6.6.6h.6v3.6h-1.2c-.33 0-.6.27-.6.6s.27.6.6.6h3.6a.6.6 0 0 0 0-1.2">
                                    </path>
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>
                <div class="css-o51ctb">
                    <div class="css-7iv403">
                        <div class="css-7tj8u">
                            <span>
                                3 pièces
                            </span>
                        </div>
                        <div class="css-7tj8u">
                            <span aria-hidden="true" style="margin-right:8px;font-size:small">
                                \u{A0}•\u{A0}
                            </span>
                                <span>
                                2 chambres
                            </span>
                        </div>
                        <div class="css-7tj8u">
                            <span aria-hidden="true" style="margin-right:8px;font-size:small">
                                \u{A0}•\u{A0}
                            </span>
                            <span>
                                57 m²
                            </span>
                        </div>
                        <div class="css-7tj8u">
                            <span aria-hidden="true" style="margin-right:8px;font-size:small">
                                \u{A0}•\u{A0}
                            </span>
                            <span>
                                Étage 2/4
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="css-1ejhcc1">
                <div class="css-e7qgrl">
                    <hr aria-hidden="true" class="css-10dx2qz">
                </div>
                <button class="css-xzcm4g" data-testid="cdp-location-address">
                    <img src="https://www.seloger.com/shared/images/map/address-map.png" alt="address-icon" class="css-2czen3">
                    <div class="css-gd6b0m">
                        <span class="css-1x2e3ne">
                            Gerland, Lyon 7ème (69007)
                        </span>
                    </div>
                    <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-11rpgr5">
                        <path d="M9.193 20.243a.6.6 0 0 1-.034-.848l6.791-7.392-6.79-7.396a.6.6 0 1 1 .881-.814l7.2 7.8a.6.6 0 0 1 0 .814l-7.2 7.8a.6.6 0 0 1-.847.036Z">
                        </path>
                    </svg>
                </button>
                <button class="css-14cyh9g" data-testid="">
                    <img src="https://www.seloger.com/shared/images/map/travel-time.png" alt="travel-time-icon" class="css-2czen3">
                    <div class="css-gd6b0m">
                        <span class="css-1x2e3ne">
                            Calculer un temps de trajet
                        </span>
                        <span class="css-dytxrd">
                            Pour aller au travail, à l’école, ...
                        </span>
                    </div>
                    <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-11rpgr5">
                        <path d="M9.193 20.243a.6.6 0 0 1-.034-.848l6.791-7.392-6.79-7.396a.6.6 0 1 1 .881-.814l7.2 7.8a.6.6 0 0 1 0 .814l-7.2 7.8a.6.6 0 0 1-.847.036Z">
                        </path>
                    </svg>
                </button>
                <div class="css-1usqc3g">
                    <hr aria-hidden="true" class="css-10dx2qz">
                </div>
            </div>
        </h1>


    https://www.seloger.com/classified-search?distributionTypes=Buy
    &estateTypes=House,Apartment
    &featuresIncluded=Parking_Garage
    &locations=eyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0
    &numberOfBedroomsMax=3
    &numberOfBedroomsMin=1
    &numberOfRoomsMax=5
    &numberOfRoomsMin=2
    &priceMax=300000
    &spaceMin=30
    &page=2
    &order=DateDesc



    <div id="classified-card-25UPSVC4HVNR" data-testid="serp-core-classified-card-testid">
        <div class="css-79elbk" data-testid="classified-card-mfe-25UPSVC4HVNR">
            <a href="https://www.seloger.com/annonces/achat/appartement/lyon-3eme-69/252861745.htm?serp_view=list&amp;search=distributionTypes%3DBuy%26estateTypes%3DHouse%2CApartment%26featuresIncluded%3DParking_Garage%26locations%3DeyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0%26numberOfBedroomsMax%3D3%26numberOfBedroomsMin%3D1%26numberOfRoomsMax%3D5%26numberOfRoomsMin%3D2%26priceMax%3D300000%26spaceMin%3D30%26order%3DDateDesc#ln=classified_search_results&amp;m=classified_search_results_classified_classified_detail_M" target="_blank" data-plus="%3Fserp_view%3Dlist%26search%3DdistributionTypes%253DBuy%2526estateTypes%253DHouse%252CApartment%2526featuresIncluded%253DParking_Garage%2526locations%253DeyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0%2526numberOfBedroomsMax%253D3%2526numberOfBedroomsMin%253D1%2526numberOfRoomsMax%253D5%2526numberOfRoomsMin%253D2%2526priceMax%253D300000%2526spaceMin%253D30%2526order%253DDateDesc%23ln%3Dclassified_search_results%26m%3Dclassified_search_results_classified_classified_detail_M" title="Appartement à vendre - Lyon 3ème - 229\u{202F}000\u{A0}€ - 2 pièces, 1 chambre, 57,6 m², RDC/5" class="css-w5uu0r" style="z-index:19" data-testid="card-mfe-covering-link-testid">
            </a>
            <div data-cardsize="M" class="css-1wbjam9" data-testid="cardmfe-container--test-id">
                <div class="css-1pyemfj">
                    <div class="css-j7qwjs">
                        <div class="css-1ie7s1l" style="z-index:21">
                    </div>
                    <div class="css-1nlih7a" data-testid="cardmfe-picture-box-test-id">
                        <div class="css-tdfz5m" data-testid="cardmfe-picture-box-opacity-layer-test-id">
                            <div data-testid="card-mfe-picture-box-gallery-thumbnail-test-id">
                                <div class="css-b7jmoi">
                                    <img src="https://mms.seloger.com/1/5/f/8/15f8c001-59a9-4196-b2f6-d27f4af03c3f.jpg?ci_seal=4197d400a980ac0967741b53575d55ae96b63884&amp;w=525&amp;h=394" alt="Appartement à vendre 229\u{202F}000\u{A0}€ 2 pièces 1 chambre 57,6 m² RDC/5 Lyon 3ème 69003" aria-label="Image principale" loading="lazy" class="css-1td0xc2" style="aspect-ratio:4/3">
                                </div>
                            </div>
                            <div class="css-1ya332l">
                                <div class="css-ru6lc2" data-testid="cardmfe-tag-testid">
                                    <div class="css-564qts" data-testid="cardmfe-tag-testid-new">
                                        <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-bduyq">
                                        <path d="M9.562 3.538c-.21-.59.318-1.159.9-1.016 1.127.277 2.215.977 3.105 1.833.891.857 1.62 1.904 2.003 2.917.346.917.447 1.795.549 2.323.05.259.093.394.128.461.027-.005.05-.014.081-.072.043-.079.079-.208.097-.384.018-.17.016-.35.01-.506l-.006-.21-.001-.096a.7.7 0 0 1 .018-.148l.041-.122c.062-.145.195-.338.47-.362a.6.6 0 0 1 .338.076 1 1 0 0 1 .18.137c.173.166.345.44.484.684.22.383.433.832.55 1.094l.08.189.11.307c1.106 3.19.844 6.644-1.82 9.106-3.382 3.124-8.656 2.356-11.072-1.3-.576-.873-1.08-2.222-1.174-3.286-.106-1.193.053-2.48.345-3.586.288-1.09.726-2.079 1.228-2.628.081-.09.168-.174.26-.237a.6.6 0 0 1 .468-.114.56.56 0 0 1 .407.351c.042.1.058.207.067.279.01.079.015.166.02.246q.007.123.017.25.013.154.04.292c.625-.94 1.312-2.043 1.763-3.171.477-1.193.66-2.335.314-3.307m.976-.007c.318 1.209.048 2.496-.422 3.671-.514 1.286-1.297 2.517-1.95 3.492v.001a.856.856 0 0 1-1.529-.213v-.001q-.061-.197-.09-.386c-.24.431-.479 1.027-.664 1.728-.27 1.023-.412 2.196-.319 3.255.079.89.523 2.088 1.021 2.841 2.1 3.18 6.71 3.855 9.663 1.126 2.301-2.126 2.592-5.136 1.569-8.085l-.104-.285c-.026-.07-.185-.427-.38-.804a1.8 1.8 0 0 1-.188.578c-.144.26-.386.487-.751.548a.9.9 0 0 1-.52-.052.9.9 0 0 1-.387-.33c-.16-.238-.234-.558-.287-.837-.12-.616-.196-1.351-.504-2.166-.324-.857-.963-1.788-1.773-2.567-.724-.696-1.558-1.243-2.385-1.514m-.248 6.843a.66.66 0 0 1 .787-.847c.91.24 1.825.995 2.312 1.645.52.696.736 1.317.827 1.806l.015.097c.229-.369.802-.417 1.048.02l.001.001c.155.276.329.711.464 1.083.131.362.253.74.281.896.73 4.078-3.606 6.394-6.51 4.39l-.017.018-.33-.278a4.6 4.6 0 0 1-.992-1.236c-.263-.475-.46-1.02-.488-1.55a6.8 6.8 0 0 1 .285-2.248c.096-.313.21-.596.331-.816.061-.109.131-.215.21-.304a.77.77 0 0 1 .364-.24l.068-.014a.56.56 0 0 1 .41.104.75.75 0 0 1 .2.232c.056.095.104.215.138.304.133-.215.28-.525.403-.888.244-.719.358-1.511.223-2.067zm1.002.25c.081.744-.087 1.575-.31 2.234a5.4 5.4 0 0 1-.508 1.107 2 2 0 0 1-.305.39c-.095.09-.277.239-.522.239-.317 0-.51-.212-.601-.346a1 1 0 0 1-.069-.114q-.056.143-.11.318a5.8 5.8 0 0 0-.244 1.916c.018.34.15.741.369 1.134.217.39.5.738.777.973l.218.17c2.285 1.643 5.688-.213 5.117-3.4l-.005-.021-.015-.056-.052-.172a13 13 0 0 0-.167-.489c-.04-.111-.086-.221-.128-.328-.071.095-.15.18-.237.246-.136.102-.373.217-.645.124a.7.7 0 0 1-.398-.364l-.06-.143a1 1 0 0 1-.042-.236c-.004-.056-.007-.13-.01-.19a3 3 0 0 0-.05-.462c-.065-.353-.224-.835-.652-1.406a4.1 4.1 0 0 0-1.351-1.124">
                                        </path>
                                        </svg>
                                        <span>
                                            Nouveau
                                        </span>
                                    </div>
                                </div>
                                <span class="css-1kjr4iz" data-testid="card-mfe-energy-performance-class">
                                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="39" height="24" style="vertical-align:baseline">
                                    <path d="M0 6q0-6 6-6h15q3 0 5 2l7 8q1 2 0 4l-7 8q-2 2-5 2h-15q-6 0-6-6v-12" fill="#e0e0e0">
                                    </path>
                                    <path d="M1 6q0-5 5-5h15q3 0 5 2l6 7q1 2 0 4l-6 7q-2 2-5 2h-15q-5 0-5-5v-12" fill="#f6ed02">
                                    </path>
                                    <text x="8" y="13" fill="#323232" dominant-baseline="middle" font-size="14px">D
                                    </text>
                                    </svg>
                                </span>
                            </div>
                            <div class="css-67999l">
                                <div class="css-k008qs">
                                    <div class="css-1wxfz8i" data-testid="cardmfe-imgCounterTag-testid">
                                        <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-bduyq">
                                        <path d="M7.8 13.2C7.8 10.88 9.679 9 12 9s4.2 1.879 4.2 4.2-1.879 4.2-4.2 4.2a4.2 4.2 0 0 1-4.2-4.2m4.2-3a3 3 0 1 0 0 6 3 3 0 1 0 0-6m4.009-5.37.39 1.17H19.2c1.324 0 2.4 1.076 2.4 2.4V18c0 1.324-1.076 2.4-2.4 2.4H4.8A2.4 2.4 0 0 1 2.4 18V8.4A2.4 2.4 0 0 1 4.8 6h2.801l.39-1.17c.244-.734.93-1.23 1.707-1.23h4.604c.777 0 1.463.496 1.707 1.23M4.8 7.2a1.2 1.2 0 0 0-1.2 1.2V18a1.2 1.2 0 0 0 1.2 1.2h14.4c.664 0 1.2-.536 1.2-1.2V8.4c0-.664-.536-1.2-1.2-1.2h-3.664l-.663-1.99a.6.6 0 0 0-.57-.41H9.697a.6.6 0 0 0-.57.41L8.464 7.2z">
                                        </path>
                                        </svg>
                                        <span>8
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="css-1l4tb01">
                    <div class="css-wb0bqw" data-testid="cardmfe-description-box-text-test-id">
                        <div class="css-sxp1de">
                            <div class="css-45nx9z">
                                <div aria-label="229000 €" class="css-1kfguso" data-testid="cardmfe-price-testid">
                                        229\u{202F}000\u{A0}€<!-- --> <span class="css-4jg6ys">3\u{202F}978\u{A0}€/m²
                                    </span>
                                </div>
                            </div>
                            <div class="css-1fa4wsc">
                                <button data-partner="https://www.creditmutuel.fr/fr/simulations/credit-immobilier-projet.html?utm_medium=desktop&amp;utm_format=texte&amp;utm_content=lien_prix_pdl&amp;utm_source=seloger&amp;utm_campaign=2025&amp;procom=SELOGER" class="css-1mhg5ff" data-testid="cc-mfe.partner-link" data-listener-attached="true">
                                    Simuler mon crédit immobilier
                                </button>
                                <div class="css-10kek7w" data-testid="cc-mfe.container-partner-link">
                                    <button aria-haspopup="dialog" aria-expanded="false" type="button" tabindex="0" data-react-aria-pressable="true" aria-label="Info" class="css-1mht32h" data-testid="cardmfe-reporting-modal-trigger-testid">
                                        <div class="css-2bxna6">
                                        </div>
                                        <div class="css-9ifxdl">
                                            <span class="css-nrs666">
                                                <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-1oij85x">
                                                <path d="M12 2.4A9.6 9.6 0 0 0 2.4 12a9.6 9.6 0 0 0 9.6 9.6 9.6 9.6 0 0 0 9.6-9.6A9.6 9.6 0 0 0 12 2.4m0 18c-4.631 0-8.4-3.769-8.4-8.4S7.369 3.6 12 3.6s8.4 3.769 8.4 8.4-3.769 8.4-8.4 8.4m0-11.1a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8m1.8 6.3h-1.2v-4.2c0-.33-.27-.6-.6-.6h-1.2c-.33 0-.6.27-.6.6s.27.6.6.6h.6v3.6h-1.2c-.33 0-.6.27-.6.6s.27.6.6.6h3.6a.6.6 0 0 0 0-1.2">
                                                </path>
                                                </svg>
                                            </span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <a href="https://www.seloger.com/annonces/achat/appartement/lyon-3eme-69/252861745.htm?serp_view=list&amp;search=distributionTypes%3DBuy%26estateTypes%3DHouse%2CApartment%26featuresIncluded%3DParking_Garage%26locations%3DeyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0%26numberOfBedroomsMax%3D3%26numberOfBedroomsMin%3D1%26numberOfRoomsMax%3D5%26numberOfRoomsMin%3D2%26priceMax%3D300000%26spaceMin%3D30%26order%3DDateDesc#ln=classified_search_results&amp;m=classified_search_results_classified_classified_detail_M" target="_blank" data-plus="%3Fserp_view%3Dlist%26search%3DdistributionTypes%253DBuy%2526estateTypes%253DHouse%252CApartment%2526featuresIncluded%253DParking_Garage%2526locations%253DeyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0%2526numberOfBedroomsMax%253D3%2526numberOfBedroomsMin%253D1%2526numberOfRoomsMax%253D5%2526numberOfRoomsMin%253D2%2526priceMax%253D300000%2526spaceMin%253D30%2526order%253DDateDesc%23ln%3Dclassified_search_results%26m%3Dclassified_search_results_classified_classified_detail_M" class="css-1a6drk4">
                            <div class="css-1n0wsen">
                                Appartement à vendre
                            </div>
                            <div class="css-1ogpjnb" data-testid="cardmfe-keyfacts-testid">
                                <div class="css-9u48bm">
                                    2 pièces
                                </div>
                                <div class="css-9u48bm">
                                    ·
                                </div>
                                <div class="css-9u48bm">
                                    1 chambre
                                </div>
                                <div class="css-9u48bm">
                                    ·
                                </div>
                                <div class="css-9u48bm">
                                    57,6 m²
                                </div>
                                <div class="css-9u48bm">
                                    ·
                                </div>
                                <div class="css-9u48bm">
                                    RDC/5
                                </div>
                            </div>
                        </a>
                        <div>
                            <div class="css-16qj5au">
                                <div class="css-oaskuq" data-testid="cardmfe-description-box-address">
                                    Lyon 3ème (69003)
                                </div>
                                <button class="css-yezoni" data-testid="cardmfe-icon-button">
                                    <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-1jjup13" data-testid="cardmfe-chevron-down-icon">
                                    <path d="M3.76 8.225C3.878 8.098 4.039 8 4.2 8a.6.6 0 0 1 .408.16L12 14.952l7.393-6.792a.6.6 0 1 1 .815.881l-7.802 7.201a.6.6 0 0 1-.815 0L3.79 9.041c-.24-.193-.255-.572-.03-.816">
                                    </path>
                                    </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="css-1is6duw" data-testid="cardmfe-descriptionwrapper-test-id">
                            <button class="css-149h444" data-testid="cardmfe-icon-button">
                                <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-1jjup13" data-testid="cardmfe-chevron-down-icon">
                                <path d="M3.76 8.225C3.878 8.098 4.039 8 4.2 8a.6.6 0 0 1 .408.16L12 14.952l7.393-6.792a.6.6 0 1 1 .815.881l-7.802 7.201a.6.6 0 0 1-.815 0L3.79 9.041c-.24-.193-.255-.572-.03-.816">
                                </path>
                                </svg>
                            </button>
                        </div>
                        <div class="css-bp05pc" data-testid="cc-mfe.action-slot">
                            <div class="css-i8gb8v" data-testid="cc-mfe.action-slot-wrapper">
                                <div class="css-k0f1n7">
                                    <div data-testid="aviv.perseus.favoriteHeart.emptyHeart">
                                        <button type="button" data-react-aria-pressable="true" aria-label="Ajouter aux favoris" class="css-a8wglt" data-testid="aviv.perseus.favoriteHeart.addButton">
                                            <svg viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="css-116jaxd">
                                            <path d="m11.966 6 .45-.45a5.46 5.46 0 0 1 4.706-1.496A5.36 5.36 0 0 1 21.6 9.34v.218a5.6 5.6 0 0 1-1.785 4.106l-6.776 6.326a1.52 1.52 0 0 1-2.078 0l-6.776-6.326A5.62 5.62 0 0 1 2.4 9.559V9.34a5.36 5.36 0 0 1 4.477-5.287A5.39 5.39 0 0 1 11.55 5.55l.416.449Zm0 1.699-1.263-1.301a4.17 4.17 0 0 0-3.627-1.16c-2.006.334-3.51 2.07-3.51 4.103v.218c0 1.226.542 2.392 1.437 3.229l6.776 6.326a.3.3 0 0 0 .187.086c.117 0 .195-.03.255-.086l6.776-6.327A4.42 4.42 0 0 0 20.4 9.56v-.22c0-2.032-1.47-3.77-3.476-4.104a4.13 4.13 0 0 0-3.627 1.16L11.967 7.7Z">
                                            </path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div data-testid="cardmfe-bottom-test-id">
                    <div class="css-1qinmvb" data-testid="cardmfe-description-text-test-id">
                        <div class="css-rw6885">
                            <hr aria-hidden="true" class="css-10dx2qz">
                        </div>
                        <div class="css-oorffy">
                            Immobilier.notaires® et l'office notarial NOTAIRES DU VAL D'OUEST, SELAS vous proposent : Appartement à vendre - LYON 3 (69003) - - - - - - - - - - - A Lyon 3ème, dans une copropriété rénovée en 2022...
                        </div>
                    </div>
                    <div class="css-sov3ii" data-testid="cardmfe-card-bottom-strip-test-id">
                        <div class="css-r5i187">
                            <span class="css-1tafjuz">
                                Notaires du Val d Ouest
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
     */





     // _____________________ \\
    // *** UTILS FUNCTIONS *** \\

    private function dumpMethod($method){
        if($this->debugMethod) {
            dump(str_repeat("_", (strlen($method) + 10)));
            dump('*** ' . $method . '() ***');
        }
    }

    private function initPantherClient($page = 1, $fullInit = true){
        $this->dumpMethod(__METHOD__);
        try {
            if($fullInit) {
                $this->killChromeProcesses();
                $this->client = Client::createChromeClient(
                    __DIR__ . '/../drivers/chromedriver.exe',
                    [
                        //'--headless=new',
                        //'--disable-gpu',
                        '--window-size=1920,1080',
                    ]
                );

                sleep(1);
                if (count($this->client->getWindowHandles()) === 0) {
                    $this->fail('Le navigateur a été fermé prématurément.');
                }
            }

            $this->crawler = $this->client->request('GET', "https://www.seloger.com/classified-search?distributionTypes=Buy".
                        "&estateTypes=House,Apartment".
                        "&locations=eyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0".
                        "&numberOfBedroomsMax=3".
                        "&numberOfBedroomsMin=1".
                        "&numberOfRoomsMax=5".
                        "&numberOfRoomsMin=2".
                        "&priceMax=300000".
                        "&spaceMin=30".
                        "&order=DateDesc".
                        "&page=".$page);
            sleep(1);
            //$crawler = $client->request('GET', 'https://verseoconseillerrecette.securimut.fr/');

            //$this->assertSame('VERSEO assurance emprunteur', $client->getTitle(), 'Le titre de la page est incorrect.');
            //$client->takeScreenshot('screenshot.png');

            //$html = $client->getPageSource();
            //file_put_contents('debug-page.html', $html);

            //$client->wait()->until(function () use ($client) {
            //    return $client->getCrawler()->filter('[name="mail"]')->count() > 0;
            //});
        } catch (\Throwable $e) {
            $this->fail('Erreur lors de l\'initialisation du client Panther : ' . $e->getMessage());
        }

        return $this->client;
    }

    private function crawlNewUrl($url){
        $this->dumpMethod(__METHOD__);
        try {
            $this->crawler = $this->client->request('GET', $url);
            sleep(1);
        } catch (\Throwable $e) {
            $this->fail('Erreur lors du crawl ('.$url.') : ' . $e->getMessage());
        }

        return $this->client;
    }


    private function killChromeProcesses(): void
    {
        $this->dumpMethod(__METHOD__);
        // Pour Windows uniquement
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @shell_exec('taskkill /F /IM chrome.exe /T >nul 2>&1');
            @shell_exec('taskkill /F /IM chromedriver.exe /T >nul 2>&1');
        }
    }


    private function assertNoSymfonyException($client, $waitElemSelector = null): void
    {
        $this->dumpMethod(__METHOD__);
        if($waitElemSelector != null){
            try{ $client->waitFor($waitElemSelector, 10); } catch (\Exception $e){}
        }

        $html = $client->executeScript('return document.documentElement.outerHTML;');

        if (str_contains($html, 'exception-message-wrapper') || str_contains($html, 'Symfony Exception')) {
            // Analyse DOM avec DOMDocument
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $messageNode = $xpath->query("//*[contains(@class, 'exception-message-wrapper')]");

            $message = $messageNode->length > 0
                ? trim($messageNode->item(0)->textContent)
                : 'Erreur Symfony détectée dans la page.';

            $this->fail("Exception Symfony détectée sur " . $client->getCurrentURL() . " : " . $message);
        }
    }

    private function clickOnElem($client, $selector, $checkText = null, $timer = 15)
    {
        $this->dumpMethod(__METHOD__);
        $this->scrollAndWaitElem($client, $selector, true, $timer);

        if($checkText){
            $btnText = $client->getCrawler()->filter($selector)->text();
            $this->assertStringContainsString($checkText,$btnText);
        }

        try {
            $client->findElement(WebDriverBy::cssSelector($selector))->click();
        } catch (\Exception $e){
            //$client->executeScript("document.querySelector('".$selector."').click();");
            $client->executeScript(
                "const elems = document.querySelectorAll('".$selector."');
                const visibleElems = Array.from(elems).filter(el => el.offsetParent !== null);

                // Par exemple, scroll au premier visible
                if (visibleElems.length > 0) {
                    visibleElems[0].click();
                }",
                []
            );
        }

        usleep(200000);
    }

    private function geXPath()
    {
        $this->dumpMethod(__METHOD__);
        $html = $this->client->executeScript('return document.documentElement.outerHTML;');

        $this->domDocument = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $this->domDocument->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return new \DOMXPath($this->domDocument);;
    }

    private function scrollAndWaitElem($client, $selector, $visibility, $timer = 15)
    {
        $this->dumpMethod(__METHOD__);
        try { $client->waitFor($selector, $timer); } catch (\Exception $e){}

        try {
            $client->executeScript(
                "const elems = document.querySelectorAll('".$selector."');
                const visibleElems = Array.from(elems).filter(el => el.offsetParent !== null);

                // Par exemple, scroll au premier visible
                if (visibleElems.length > 0) {
                    visibleElems[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }",
                []
            );
            usleep(100000);
        } catch (\Exception $e){}

        try {
            if($visibility === true){
                $client->waitForVisibility($selector, 6);
            } elseif($visibility === false) {
                $client->waitFor($selector, 6);
            }
        } catch (\Exception $e){}
    }
}

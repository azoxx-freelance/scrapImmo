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
            $url = "https://www.seloger.com/classified-search?distributionTypes=Buy".
                "&estateTypes=House,Apartment".
                "&locations=eyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0".
                "&numberOfBedroomsMax=3".
                "&numberOfBedroomsMin=1".
                "&numberOfRoomsMax=5".
                "&numberOfRoomsMin=2".
                "&priceMax=300000".
                "&spaceMin=30".
                "&order=DateDesc".
                "&page=".$page;
            $this->initPantherClient($page, $url, ($page == $startPage));
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

        $newAnnonces = $annonceRepo->findBy(['status' => null, 'source' => Annonce::$sourceArray['SL']]);
        dump("New Annonce: " . count($newAnnonces));
        $caracList = Caracteristique::$caracteristiquesCategories;

        /** @var Annonce $annonce */
        foreach ($newAnnonces as $annonce){
            dump((new \DateTime())->format('H:i:s')." - #" . $annonce->getId());
            $this->crawlNewUrl($annonce->getLien());
            $xpath = $this->geXPath();
            $this->client->getWebDriver()->executeScript("UC_UI.denyAllConsents().then(UC_UI.closeCMP);");
            //$outerHtml = $this->domDocument->saveHTML($nodeInfo[0]);

            /////////////// DELETED ///////////////
            $nodes = iterator_to_array($xpath->query('//div[contains(normalize-space(.), "Annonce supprimée")]'));
            $nodes = array_merge($nodes, iterator_to_array($xpath->query('//div[contains(normalize-space(.), "Cette annonce n\'est plus disponible")]')));
            if(count($nodes) > 0) {
                $annonce->setStatus('DELETED');
                $this->em->flush();
                continue;
            }

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
            $listComplete = $xpath->query( '//div[@role="dialog"]//li');
            $listResume = (count($listComplete) > 0)?[]:$xpath->query( '//section[@data-testid="cdp-features"]//ul//li');
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
            $annonce->setIsActive(true);
            $this->em->flush();
        }

        dump('flush');
        $this->em->flush();
        dump('END');

        sleep(20);
    }





    public function testScrapAnnonceLeBonCoin()
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
            $url = "https://www.leboncoin.fr/recherche".
                "?category=9".
                "&locations=Lyon_69003__45.75898_4.86114_3774_1000".
                "&price=20000-300000".
                "&square=30-max".
                "&bedrooms=1-max".
                "&rooms=2-max".
                "&real_estate_type=2".
                "&global_condition=3%2C2%2C1".
                "&sort=time".
                "&order=desc".
                "&page=".$page;
            $this->initPantherClient($page, $url, ($page == $startPage));
            $this->assertNoSymfonyException($this->client, '[data-testid="serp-core-scrollablelistview-testid"]');
            $xpath = $this->geXPath();
            $nodes = $xpath->query('//*'.'ul[@data-test-id="listing-column"]//a[@aria-label="Voir l’annonce"]');
            dump('Page : ' . $page);
            dump('Nombre de blocs trouvés : ' . $nodes->length);

            $result = ['added' => [], 'exist' => []];

            foreach ($nodes as $node) {
                if ($node->nodeName !== 'a') {
                    continue;
                }

                $href = $node->getAttribute('href');
                if (preg_match('/\/([A-Za-z0-9]+)$/', $href, $matches)) {
                    $idAnnonce = $matches[1];

                    $existingById = $annonceRepo->findOneBy(['id' => $idAnnonce]);
                    $existingByUrl = $annonceRepo->findOneBy(['lien' => $href]);

                    if (!$existingById && !$existingByUrl) {
                        $annonce = new Annonce();
                        $annonce->setId($idAnnonce);
                        $annonce->setLien($href);
                        $annonce->setSource(Annonce::$sourceArray['LBC']);
                        $this->em->persist($annonce);
                        $result['added'][] = $idAnnonce;
                    } else {
                        $result['exist'][] = $idAnnonce;
                    }
                } else {
                    dump("Impossible d’extraire l’ID pour : $href");
                }
            }

            dump("Ajouté: " . count($result['added']));
            dump("Existante: " . count($result['exist']));
            //dump($result);

            //if(count($result['added']) == 0 && count($result['exist']) == 0){
            if(count($result['added']) == 0){
                $hasResult = false;
            }

            $hasResult = false;

            $this->em->flush();
            $page++;
        }


        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        /////////////////////////////////////////// FILL NEW ANNONCES ///////////////////////////////////////////
        /////////////////////////////////////////////////////////////////////////////////////////////////////////

        $newAnnonces = $annonceRepo->findBy(['status' => null, 'source' => Annonce::$sourceArray['LBC']]);
        dump("New Annonce: " . count($newAnnonces));
        $caracList = Caracteristique::$caracteristiquesCategories;

        /** @var Annonce $annonce */
        foreach ($newAnnonces as $annonce){
            dump((new \DateTime())->format('H:i:s')." - #" . $annonce->getId());
            $this->crawlNewUrl($annonce->getLien());
            $xpath = $this->geXPath();
            $this->client->getWebDriver()->executeScript("UC_UI.denyAllConsents().then(UC_UI.closeCMP);");
            //$outerHtml = $this->domDocument->saveHTML($nodeInfo[0]);

            // TODO : https://www.leboncoin.fr/ad/ventes_immobilieres/3080386382
            // - Voir + desc
            // - show carac full list
            // - show carac.carac detail

            // photos, titre, dpe, ges, caracteristique, etageMax

            /////////////// DELETED ///////////////
            /*$nodes = iterator_to_array($xpath->query('//div[contains(normalize-space(.), "Annonce supprimée")]'));
            $nodes = array_merge($nodes, iterator_to_array($xpath->query('//div[contains(normalize-space(.), "Cette annonce n\'est plus disponible")]')));
            if(count($nodes) > 0) {
                $annonce->setStatus('DELETED');
                $this->em->flush();
                continue;
            }//*/

            /////////////// PRICE ///////////////
            $valueRaw = $xpath->evaluate('string(//div[@data-qa-id="adview_price"])');
            $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode($valueRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $annonce->setPrix(intval(str_replace(' ', '', $value)));

            /////////////// DESC ///////////////
            $descNodeList = $xpath->query('//div[@data-qa-id="adview_description_container"]');
            if ($descNodeList->length > 0) {
                $descNode = $descNodeList->item(0);
                $desc = $descNode->textContent;
                $annonce->setDescription($desc);
            }

            /////////////// DESC ///////////////
            $nodeList = $xpath->query('//div[@data-test-id="location-map-title"]');
            if ($descNodeList->length > 0) {
                $node = $descNodeList->item(0);

                $keyValueNodes = $xpath->query( './/p', $node);
                $quartierItem = $keyValueNodes->item(0)->textContent;
                $villeItem = $keyValueNodes->item(1)->textContent;

                $annonce->setQuartier($quartierItem);
                $annonce->setVille($villeItem);
                $annonce->setAdresse($quartierItem . ', ' . $villeItem);
            }

            /////////////// TYPE ///////////////

            $list = $xpath->query( '//div[@data-qa-id="criteria_container"]//div[@data-test-id="criteria"]');
            foreach ($list as $item) {
                $keyValueNodes = $xpath->query( './/p', $item);
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

            $annonce->setStatus('SCRAPED');
            $annonce->setIsActive(true);
            $this->em->flush();
        }

        dump('flush');
        $this->em->flush();
        dump('END');

        sleep(20);
    }

    /*
        https://www.leboncoin.fr/recherche
        ?category=9
        &locations=Lyon_69003__45.75898_4.86114_3774_1000
        &price=20000-300000
        &square=30-max
        &bedrooms=1-max
        &rooms=2-max
        &real_estate_type=2
        &global_condition=3%2C2%2C1
        &sort=time
        &order=desc
        &page=2
    */







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

*/





     // _____________________ \\
    // *** UTILS FUNCTIONS *** \\

    private function dumpMethod($method){
        if($this->debugMethod) {
            dump(str_repeat("_", (strlen($method) + 10)));
            dump('*** ' . $method . '() ***');
        }
    }

    private function initPantherClient($page = 1, $url = '', $fullInit = true){
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

            $this->crawler = $this->client->request('GET', $url);
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

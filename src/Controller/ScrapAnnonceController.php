<?php
/*
Home
reset filters
Filters on links
Filters on Giveaway // ended Status, Close, delete GV
new card label
Download link increase stat on click
repair logger

select tool
resize image UI
resize image size upload
refactor links (https://citizen.freshkiwi.net/)
refactor GV page

Add number of selection on head filter
multinavigator
SEO
modify head
traduction
check .net url
*/

namespace App\Controller;

use App\Entity\Annonce;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Panther\Client;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/scrap", name="scrap_")
 */
class ScrapAnnonceController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/", name="main")
     */
    public function index(Request $request): Response
    {
        $this->client = $this->initPantherClient();
        $this->assertNoSymfonyException($this->client, '[data-testid="serp-core-scrollablelistview-testid"]');
        sleep(1);

        $this->crawler = $this->client->refreshCrawler();

        //$nodes = $this->getNodesFromSelector('[@data-testid="serp-core-classified-card-testid"]');
        $nodes = $this->getNodesFromSelector('[@data-testid="card-mfe-covering-link-testid"]');

        $annonceRepo = $this->entityManager->getRepository(Annonce::class);

        foreach ($nodes as $node) {
            if ($node->nodeName !== 'a') {
                continue;
            }

            $href = $node->getAttribute('href');
            $hrefClean = explode('?', $href)[0];

            // ✅ Extraction de l’ID via regex
            if (preg_match('/\/(\d+)\.htm$/', $hrefClean, $matches)) {
                $idAnnonce = $matches[1];

                $existingById = $annonceRepo->findOneBy(['id' => $idAnnonce]);
                $existingByUrl = $annonceRepo->findOneBy(['lien' => $hrefClean]);

                if (!$existingById && !$existingByUrl) {
                    $annonce = new Annonce();
                    $annonce->setId($idAnnonce);
                    $annonce->setLien($hrefClean);
                    $annonce->setCreatedAt(new \DateTimeImmutable());
                    $this->entityManager->persist($annonce);
                    dump("Nouvelle annonce ajoutée : $idAnnonce");

                } else {
                    dump("Déjà existante : $hrefClean");
                }
            } else {
                dump("Impossible d’extraire l’ID pour : $hrefClean");
            }
        }

        sleep(2);

        return new JsonResponse([]);
    }



    /*
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
        dump(str_repeat("_", (strlen($method)+10)));
        dump('*** '.$method.'() ***');
    }

    private function initPantherClient($client = null){
        $this->dumpMethod(__METHOD__);
        try {
            $this->killChromeProcesses();
            $client = Client::createChromeClient(
                __DIR__ . '/../drivers/chromedriver.exe',
                [
                    //'--headless=new',
                    //'--disable-gpu',
                    '--window-size=1920,1080',
                ]
            );

            sleep(1);
            if (count($client->getWindowHandles()) === 0) {
                $this->fail('Le navigateur a été fermé prématurément.');
            }

            $this->crawler = $client->request('GET', "https://www.seloger.com/classified-search?distributionTypes=Buy".
                "&estateTypes=House,Apartment".
                "&featuresIncluded=Parking_Garage".
                "&locations=eyJwbGFjZUlkIjoiUE9DT0ZSNDQ0NyIsInJhZGl1cyI6MywicG9seWxpbmUiOiJffWx2R29rdVxcZkJibkBySGhsQHBOdmhAfFNwY0BuWGBdYlxcZlVyXnZMel9AckN6X0B1Q3Bed0xiXFxpVW5YYV18U3FjQHBOdWhAcEhpbEBmQmFuQGdCYW5AcUhnbEBxTndoQH1TcWNAb1hfXWNcXGtVcV53THtfQHNDe19AckNzXnRMY1xcaFVvWH5cXH1TcGNAcU52aEBzSGhsQGdCYm5AIiwiY29vcmRpbmF0ZXMiOnsibGF0Ijo0NS43NTU3Njc0Nzk4ODMxMiwibG5nIjo0Ljg2NTk5NzgwMjQ5NjI2fX0".
                "&numberOfBedroomsMax=3".
                "&numberOfBedroomsMin=1".
                "&numberOfRoomsMax=5".
                "&numberOfRoomsMin=2".
                "&priceMax=300000".
                "&spaceMin=30".
                "&order=DateDesc");
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

        return $client;
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

    private function clickOnElem($client, $selector, $checkText = null)
    {
        $this->dumpMethod(__METHOD__);
        $this->scrollAndWaitElem($client, $selector, true);

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
    }

    private function getNodesFromSelector($selector)
    {
        $html = $this->client->executeScript('return document.documentElement.outerHTML;');

        $this->domDocument = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $this->domDocument->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($this->domDocument);
        $query = '//*'.$selector;
        $nodes = $xpath->query($query);

        dump('Nombre de blocs trouvés : ' . $nodes->length);

        return $nodes;
    }

    private function scrollAndWaitElem($client, $selector, $visibility)
    {
        $this->dumpMethod(__METHOD__);
        try { $client->waitFor($selector, 15); } catch (\Exception $e){}

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

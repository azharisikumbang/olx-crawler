<?php
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\Panther\Client;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    $result = [];

    echo "Starting client..\n";
    $baseUrl = "https://www.olx.co.id";
    $targetUrl = $baseUrl . "/mobil-bekas_c198?filter=make_eq_mobil-bekas-toyota";
    $targetItemParentClassName = ".CBBsu";
    $targetItemClassName = "._3V_Ww";
    $targetLoadMoreButtonClassName = ".rui-htytx";

    $client = Client::createFirefoxClient();
    $client->request('GET', $targetUrl);
    $client->waitFor($targetItemClassName);

    $crawler = $client->getCrawler();

    echo "Loading data from $targetUrl";
    for ($i = 0; $i < 10; $i++)
    {
        echo ".";
        $client->waitFor($targetLoadMoreButtonClassName);
        $loadMoreButton = $crawler->filter($targetLoadMoreButtonClassName)->click();
    }
    echo "\n";
    echo "Total found : " . $crawler->filter($targetItemClassName)->count() . " items\n";

    $links = $crawler
        ->filter($targetItemClassName)
        ->each(function (Crawler $node) use ($baseUrl, $client) {
            return $node
                ->children(selector: 'a')
                ->link()->getUri();
        });

    $client->quit();

    $file = fopen("files.csv", "a");

    $no = 1;
    foreach ($links as $link)
    {
        echo "#$no: $link";
        $client = Client::createFirefoxClient();
        $client->request('GET', $link);
        waitForDomElement($client, 'h1');

        $crawler = $client->getCrawler();

        echo " [DONE]\n";

        fputcsv($file, [
            $crawler->filter('h1')->text(),
            $crawler->filter('.BxCeR')->text(),
            $crawler->filter('div[data-aut-id="itemPrice"]')->text(),
            $crawler->filter('h2[data-aut-id="itemAttribute_transmission"]')->text(),
            $crawler->filter('div[data-aut-id="itemAttribute_mileage"]')->text(),
            $crawler->filter('h2[data-aut-id="itemAttribute_fuel"]')->text(),
            $link,
        ]);

        $no++;

        sleep(2);
        $client->quit();
    }

    fclose($file);

    echo "All data has been written to csv.\n";

} catch (Exception $e)
{
    echo $e->getMessage();
} finally
{
    echo "Client closed\n";
}

function waitForDomElement(Client $client, string $cssSelector)
{
    $client->wait()->until(
        static function (WebDriver $driver) use ($cssSelector) {
            try
            {
                return $driver->findElement(WebDriverBy::cssSelector($cssSelector));
            } catch (StaleElementReferenceException $e)
            {
                return null;
            } catch (NoSuchElementException $e)
            {
                return null;
            }
        }
    );
}
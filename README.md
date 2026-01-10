# php-examples
Deze repository dient als voorbeeld van mijn manier van programmeren. 

De code is wat algemener gemaakt en opgeschoond. De code is van werken met Wordpress/WooCommerce en Laravel(komt nog) en zal waar nodig geüpdate worden.

## Wordpress Snippets
Onder de wordpress map staan de volgende snippets.

- <b>ApiCalls</b> - Verbinding maken met externe api's
- <b>ExternalOrder</b> - Class die externe bestellingen van een api call omzet naar WooCommerce bestellingen
- <b>PluginBase</b> - Basis class voor een wordpress plugin. Deze wordt als eerste aangeroepen na de plugin_hook
- <b>SettingsManager</b> - Renderd settings en beheert het laden en opslaan daarvan.

Dingen die ik anders zou doen met mijn code is, consitentere naming convention toepassen. Die beter te lezen is en intuitief werkt. Voorbeeld daarvan is de functies van ApiCalls. Dit is een static singleton, hierdoor leest het beter om ApiCalls::getOrders() te doen, ipv de eerdere ApiCalls::apiGetOrders(). Dit is dubbel op.

Bij ExternalOrder zou ik voor een andere versie gebruik maken van de Builder pattern. De achterliggende gedachte is dat het dan niet alleen makkelijker is in gebruik, maar ook beter te lezen. In plaats van meerdere lijnen in een array werken, per regel een functie aanroep die de order vult met de nodige data. Ook geeft dit de optie om data van de api in een functie te parsen en valideren voor een WooCommerce Order.

De SettingsManager zou ik de html nog scheiden van de class en het inrichten als components/pages zoals je in andere frameworks zou zien.

Voor de PluginBase zou ik nog kijken of er een betere structuur mogelijk is en mogelijk een handler voor het laden van de resources. Plus het het gebruik van caching om niet elke keer alle resources opnieuw te laden.1


## Symfony
Symfony 7 leren door het volgen van <a href="https://symfonycasts.com/screencast/symfony/setup">deze</a> tutorial.

## Schema.org
Schema.org je otevřený komunitní projekt vytvořený hlavními vyhledávači (Google, Bing, Yahoo! a Yandex). Jeho cílem je poskytnout standardizovaný způsob označování strukturovaných dat, který pomáhá vývojářům webových stránek efektivněji předávat informace o obsahu webových stránek vyhledávačům. Tím se zlepšuje porozumění obsahu webu ze strany vyhledávačů.

## Změny
Přidáno kód
```
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "{{product.name}}",
    "image": "http://localhost:8000/images/{{product.getImageUrls()[0]}}",
    "description": "{{product.description}}",
    "brand": "{{shopInfo.getEshopName()}}",
    "offers": {
        "@type": "Offer",
        "price": "{{product.price}}",
        "priceCurrency": "{{product.getCurrency().getName}}",
        "availability": "https://schema.org/InStock"
    }
}
</script>
```
v souboru eshop_frontweb/templates/eshop_product/index.html.twig
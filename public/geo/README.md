# Geometries dels mapes (GeoJSON)

Els mapes de `mapes.php` intenten carregar els fitxers d'aquesta carpeta
primer, i només si no hi són, baixen les geometries d'internet (jsdelivr /
GitHub) automàticament. Si l'ordinador on funciona l'aplicació no té accés
a internet, o el proxy de l'ajuntament bloqueja aquests dominis, cal baixar
els fitxers manualment un sol cop i posar-los aquí amb aquests noms exactes:

| Fitxer a crear aquí       | Font recomanada (botó "Download raw file" a GitHub) |
|----------------------------|------------------------------------------------------|
| `comarques.geojson`        | https://github.com/aariste/GeoJSON-Mapas/blob/master/comarques-compressed.geojson |
| `provincies.geojson`       | https://github.com/codeforgermany/click_that_hood/blob/main/public/data/spain-provinces.geojson |
| `paisos.geojson`           | https://github.com/johan/world.geo.json/blob/master/countries.geo.json |

Un cop hi siguin, `mapes.php` els farà servir directament sense necessitat
de connexió a internet.

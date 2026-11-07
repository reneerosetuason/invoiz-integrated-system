# Philippines locality coordinate reference

Source: [GeoNames Philippines geographic extract](https://download.geonames.org/export/dump/PH.zip), retrieved 2026-11-06.
License: [Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/).
Credit: [GeoNames](https://www.geonames.org/).

`ph-locality-coordinates.json` is derived from PH.txt. It includes ADM3
municipalities/cities and NCR ADM2 entries, joined to ADM2 province names.
Retained fields are locality name, province name, latitude, longitude and the
source GeoNames identifier. NCR entries use Metro Manila as the province.
It contains 1,650 reference entries. No seller addresses are part of this file.

Coordinates represent administrative localities, not building locations.
Pickup recommendations use them only
when exact saved coordinates are missing, and label the resulting distances as
approximate. Ambiguous same-name entries more than 5 km apart within a province
are not resolved automatically. Straight-line distance does not imply driving
distance or guaranteed service coverage. There are no runtime geocoding requests.

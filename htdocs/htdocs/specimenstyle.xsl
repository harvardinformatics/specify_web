<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
xmlns:dwc="http://rs.tdwg.org/dwc/terms/"
xmlns:dwciri="http://rs.tdwg.org/dwc/iri/"
xmlns:dcterms="http://purl.org/dc/terms/"
>

<xsl:template match="/rdf:RDF">
  <html>
  <head>
  <title>HUH Collection Object Record</title>
  </head>
  <body>
  <h2>HUH Collection Object Record</h2>
  <table border="1">
    <tr bgcolor="#b8c0d2">
      <th>Institution</th>
      <th>Herbarium</th>
      <th>Barcode</th>
      <th>Continent</th>
      <th>Country</th>
      <th>PrimaryDivision</th>
      <th>Locality</th>
      <th>ScientificName</th>
      <th>ScientificNameAuthorship</th>
      <th>Modified</th>
      <th>Collector</th>
    </tr>
    <xsl:for-each select="dwc:Occurrence">
    <tr>
      <td>Harvard University</td>
      <td><xsl:value-of select="dwc:collectionCode"/></td>
      <td>
         <a>
            <xsl:attribute name="href">
              <xsl:value-of select="dcterms:references/attribute::rdf:resource"/>
            </xsl:attribute>
            <xsl:value-of select="dwc:catalogNumber"/>
         </a>
      </td>
      <td><xsl:value-of select="dwc:continent"/></td>
      <td><xsl:value-of select="dwc:country"/></td>
      <td><xsl:value-of select="dwc:stateProvince"/></td>
      <td><xsl:value-of select="dwc:locality"/></td>
      <td><xsl:value-of select="dwc:scientificName"/></td>
      <td><xsl:value-of select="dwc:scientificNameAuthorship"/></td>
      <td><xsl:value-of select="dcterms:modified"/></td>
      <td>
         <a>
            <xsl:attribute name="href">
              <xsl:value-of select="dwciri:recordedBy/attribute::rdf:resource"/>
            </xsl:attribute>
            <xsl:value-of select="dwc:recordedBy"/>
         </a>
      </td>
    </tr>
    </xsl:for-each>
  </table>
  </body>
  </html>
</xsl:template>

</xsl:stylesheet> 

<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
xmlns:dwc="http://rs.tdwg.org/dwc/terms/"
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
    </tr>
    <xsl:for-each select="dwc:Occurrence">
    <tr>
      <td>Harvard University</td>
      <td><xsl:value-of select="dwc:collectionCode"/></td>
      <td><xsl:value-of select="dwc:catalogNumber"/></td>
    </tr>
    </xsl:for-each>
  </table>
  </body>
  </html>
</xsl:template>

</xsl:stylesheet> 

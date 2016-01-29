# Field: Reflection

Populate this field's value using values from other fields in the same entry. Uses XPath and optionally XSLT.

## Installation

1. Upload the `/reflectionfield` folder to your Symphony `/extensions` folder.

2. Enable it by selecting the "Field: Reflection", choose Enable/Install from the dropdown, then click Apply.

4. You can now add the "Reflection" field to your sections.


## Usage with XML Importer

Reflection Field assumes that the entry has already saved some data for the said field, and then seeks to update the database directly with the correct reflection generated content.
In the case of XML Importer this means that you have to include the Reflection field within the import items, otherwise XML Importer will not find any data within your database to update.
You can insert an empty string, this would be sufficient for Reflection Field to be able to save the necessary data.

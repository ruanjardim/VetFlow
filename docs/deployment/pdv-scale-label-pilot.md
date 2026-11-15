# PDV Scale-Label Pilot

This runbook validates the model-independent weight-label integration. VetFlow
can generate an adhesive EAN-13 label through the browser, or a compatible scale
can print the same variable-weight layout. A USB barcode reader sends either
label to the VetFlow PDV as keyboard input.

## Supported Boundary

- Exactly 13 numeric digits with a valid EAN-13 check digit.
- An exact clinic-configured prefix from one to four digits.
- A fixed-width local product code immediately after the prefix.
- The remaining payload digits represent kilograms with zero to three
  configured decimal places.
- The matching active product belongs to the same clinic, has unit `kg`, and
  has a sale price per kilogram.
- VetFlow-generated labels additionally validate current sellable stock, render
  the current price per kilogram and total, and support bounded physical
  dimensions, copies, and optional origin lot/expiration.
- Online and prepared encrypted-offline PDV operation.

The release does not communicate directly with a USB/serial scale, read a
stable weight from a device, or claim compatibility with price-encoded labels.
Those paths require the exact manufacturer, model and protocol.

## Safe Pilot Procedure

1. Use a dedicated fictitious pilot clinic and a non-production product.
2. In **Vendas > Balança e etiquetas**, configure the prefix and field widths.
   When a scale will also print labels, copy its exact layout from the manual or
   a manufacturer-generated test label.
3. Save one product with unit `kg`, price per kilogram and its exact local scale
   code, including leading zeros.
4. In **Gerar etiqueta por peso**, match the configured paper dimensions and
   driver, keep 100% scale with no margins or browser headers, then print at
   least `0.125 kg`, `1.000 kg` and the largest expected weight. When a scale
   also prints labels, repeat the three samples there.
5. Scan each label in an empty PDV cart and compare the displayed quantity,
   price per kilogram and line total with the printed content.
6. Change one digit in a copied label and confirm VetFlow rejects its checksum.
7. Use an unknown product code and confirm VetFlow does not offer quick product
   registration for the scale label.
8. Complete one fictitious paid sale and confirm the fractional stock exit,
   receipt total, financial income and cash-session amount.
9. Refresh the offline catalog, disconnect the network after the PDV is open,
   scan a new test label, queue the fictitious sale and verify the same stock
   effect after synchronization.

## Go/No-Go Evidence

Record the clinic, printer/driver, physical paper size, scale manufacturer/model
and firmware when applicable, label layout, three sample labels, operator,
browser, sale ID and expected versus actual quantity/total. The pilot is
**no-go** if a barcode cannot be read, content is clipped, any sample needs
manual quantity or price correction, leading zeros are lost, the check digit is
not rejected, or a manufacturer label encodes total price instead of weight.

# PDV Scale-Label Pilot

This runbook validates the first model-independent scale integration: a scale
prints an EAN-13 variable-weight label and a USB barcode reader sends that label
to the VetFlow PDV as keyboard input.

## Supported Boundary

- Exactly 13 numeric digits with a valid EAN-13 check digit.
- An exact clinic-configured prefix from one to four digits.
- A fixed-width local product code immediately after the prefix.
- The remaining payload digits represent kilograms with zero to three
  configured decimal places.
- The matching active product belongs to the same clinic, has unit `kg`, and
  has a sale price per kilogram.
- Online and prepared encrypted-offline PDV operation.

The release does not communicate directly with a USB/serial scale, read a
stable weight from a device, or claim compatibility with price-encoded labels.
Those paths require the exact manufacturer, model and protocol.

## Safe Pilot Procedure

1. Use a dedicated fictitious pilot clinic and a non-production product.
2. In **Vendas > Balança e etiquetas**, copy the prefix and field widths from
   the scale manual or a manufacturer-generated test label.
3. Save one product with unit `kg`, price per kilogram and its exact local scale
   code, including leading zeros.
4. Print labels for at least `0.125 kg`, `1.000 kg` and the largest expected
   weight. Scan each one in an empty PDV cart and compare the displayed
   quantity and line total with the scale.
5. Change one digit in a copied label and confirm VetFlow rejects its checksum.
6. Use an unknown product code and confirm VetFlow does not offer quick product
   registration for the scale label.
7. Complete one fictitious paid sale and confirm the fractional stock exit,
   receipt total, financial income and cash-session amount.
8. Refresh the offline catalog, disconnect the network after the PDV is open,
   scan a new test label, queue the fictitious sale and verify the same stock
   effect after synchronization.

## Go/No-Go Evidence

Record the clinic, scale manufacturer/model, firmware if visible, label layout,
three sample labels, operator, browser, sale ID and expected versus actual
quantity/total. The pilot is **no-go** if any sample needs manual quantity or
price correction, if leading zeros are lost, if the check digit is not
rejected, or if the label encodes total price instead of weight.

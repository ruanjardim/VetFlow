# Commissions Module

## Scope

The Commissions module configures clinic-scoped percentage rules for existing
system users acting as salespeople and produces an operational preview by
period.

## Current Behavior

- Each active rule belongs to one clinic and one active collaborator.
- An active rule cannot overlap another active rule for the same collaborator.
- The commission base can be net sold value or gross profit.
- Recognition can occur on the sale date or on the receipt date.
- Rules can require a sale to be fully paid before it is eligible.
- When receipt-date recognition allows partial settlements, only the portion
  received in the filtered period is used as the commission base.
- The preview never creates financial transactions, payables, or payments.

## Operational Boundary

Commission settlement is intentionally a later step. Before creating payable
entries, the clinic must define its payroll policy, cancellation and return
handling, approval flow, and payment responsibility.

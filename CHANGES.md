- Docblock and README updates

- The Common\Select class, when binding values from a subselect, now checks for
  `instanceof SubselectInterface` instead of `self`; the Select class now
  implements SubselectInterface, so this should not be a BC break.

- Subselects bound as where/having/etc conditions should now retain ?-bound
  params.

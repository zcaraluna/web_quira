-- Agregar comilla final a las unidades que tienen comilla de apertura pero no de cierre
UPDATE preinscriptos 
SET unidad = unidad || '"'
WHERE unidad LIKE '%"JOSE MERLO SARAVIA' 
  AND unidad NOT LIKE '%"JOSE MERLO SARAVIA"';

UPDATE preinscriptos 
SET unidad = unidad || '"'
WHERE unidad LIKE '%"Gral. JOSE E. DÍAZ' 
  AND unidad NOT LIKE '%"Gral. JOSE E. DÍAZ"';


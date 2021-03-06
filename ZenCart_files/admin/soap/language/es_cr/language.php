<?php
//
// * @copyright Copyright 2010 PhreeSoft LLC
//  $Id: /admin/soap/language/es_cr/language.php $
//
// Set some defines to use based on zencart install
define('ZENCART_PRODUCT_TAX_CLASS_ID',2); // sets the record id for the default sales tax to use

// General defines for SOAP interface
define('SOAP_NO_USER_PW','El nombre de usuario y contraseña no se puedieron encontrar en el guión XML.');
define('SOAP_USER_NOT_FOUND','El nombre de usuario no es válido.');
define('SOAP_PASSWORD_NOT_FOUND','La contraseña no es válida.');
define('SOAP_UNEXPECTED_ERROR','El servidor de proceso devolvió un error inesperado.');
define('SOAP_XML_SUBMITTED_SO','Se entregó una Órden de Venta en formato XML');
define('SOAP_NO_CUSTOMER_ID','Falta la identificación del cliente o la identificación es inválida.');
define('SOAP_NO_POST_DATE','La fecha de la transacción no se encontró o es inválida.');
define('SOAP_BAD_POST_DATE','La fecha de la transacción no está dentro de los años fiscales actualmente definidos en PhreeBooks.');

define('SOAP_BAD_LANGUAGE_CODE','El código ISO de idioma no se pudo encontrar en la tabla de idiomas de ZenCart. Se esperaba encontrar el código = ');
define('SOAP_BAD_PRODUCT_TYPE','No se encontró el nombre del tipo de producto en la tabla product_types ZenCart. Se esperaba encontrar type_name %s para el código (sku) %s.');
define('SOAP_BAD_MANUFACTURER','No se encontró el nombre del fabricante en la tabla de fabricantes de osCommerce. Se esperaba encontrar el nombre de fabricante name %s para código (sku) %s.');
define('SOAP_BAD_CATEGORY','No se encontró el nombre de la categoría o el nombre no es único en la tabla categories_description de ZenCart. Se esperaba encontrar el nombre de la categoría %s para el código (sku) %s.');
define('SOAP_BAD_CATEGORY_A','La categoría no esta en el nivel de categorías mas bajo del árbol de categorías. ¡Esto es un requisito de ZenCart!');
define('SOAP_NO_SKU','No se encontró el código (SKU).  ¡El código (SKU) debe estar declarado en el guión XML!');
define('SOAP_BAD_ACTION','El guión contienen una acción no permitida.');
define('SOAP_OPEN_FAILED','Hubo un error al abrir el archivo de escritura. Se intentó escribir a: ');
define('SOAP_ERROR_WRITING_IMAGE','Hubo un error al intentar escribir el archivo al directorio de ZenCart.');

define('SOAP_PU_POST_ERROR','Hubo un error actualizando el producto en ZenCart. Descripción - ');
define('SOAP_PRODUCT_UPLOAD_SUCCESS_A','Código (SKU) del producto ');
define('SOAP_PRODUCT_UPLOAD_SUCCESS_B',' fue subido exitósamente.');

define('SOAP_NO_ORDERS_TO_CONFIRM', 'No hay órdenes subidas para confirmar.');
define('SOAP_CONFIRM_SUCCESS','Se completo exitósamente la confirmación de la órden. El número de órdenes actualizadas fue: %s');

define('SOAP_NO_SKUS_UPLOADED','No se subieron códigos (sku) para sincronizar.');
define('SOAP_SKUS_MISSING','Los siguientes códigos (sku) están en ZenCart pero no están registrados en PhreeBooks como que lo están: ');
define('SOAP_PRODUCTS_IN_SYNC','La lista de productos está sincronizada entre PhreeBooks y ZenCart.');
?>

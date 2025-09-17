jQuery(function($) {
  // Populate departamentos
  var $departamento = $('#shipping_state_id');
  var $municipio = $('#shipping_county_id');

  $.each(DANE_DATA, function(code, depto) {
      $departamento.append(
          $('<option>', { value: code }).text(depto.name)
      );
  });

  // When departamento changes → load municipios
  $departamento.on('change', function() {
      var code = $(this).val();
      $municipio.empty().append('<option value="">Seleccione...</option>');

      if (DANE_DATA[code]) {
          $.each(DANE_DATA[code].municipios, function(muniCode, muniName) {
              $municipio.append(
                  $('<option>', { value: muniCode }).text(muniName)
              );
          });
      }
  });
});

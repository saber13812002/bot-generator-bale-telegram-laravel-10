<?php
return [
    'accepted'             => 'O campo :attribute deve ser aceito.',
    'accepted_if'          => 'O campo :attribute deve ser aceito quando :other for :value.',
    'active_url'           => 'O campo :attribute deve ser um URL válido.',
    'after'                => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal'       => 'O campo :attribute deve ser uma data posterior ou igual a :date.',
    'alpha'                => 'O campo :attribute deve conter apenas letras.',
    'alpha_dash'           => 'O campo :attribute deve conter apenas letras, números, traços e sublinhados.',
    'alpha_num'            => 'O campo :attribute deve conter apenas letras e números.',
    'array'                => 'O campo :attribute deve ser uma matriz.',
    'ascii'                => 'O campo :attribute deve conter apenas caracteres alfanuméricos de byte único e símbolos.',
    'before'               => 'O campo :attribute deve ser uma data anterior a :date.',
    'before_or_equal'      => 'O campo :attribute deve ser uma data anterior ou igual a :date.',
    'between'              => [
        'array'   => 'O campo :attribute deve ter entre :min e :max itens.',
        'file'    => 'O campo :attribute deve estar entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string'  => 'O campo :attribute deve estar entre os caracteres :min e :max.',
    ],
    'boolean'              => 'O campo :attribute deve ser verdadeiro ou falso.',
    'can'                  => 'O campo :attribute contém um valor não autorizado.',
    'confirmed'            => 'A confirmação do campo :attribute não corresponde.',
    'current_password'     => 'A senha está incorreta.',
    'date'                 => 'O campo :attribute deve ser uma data válida.',
    'date_equals'          => 'O campo :attribute deve ser uma data igual a :date.',
    'date_format'          => 'O campo :attribute deve corresponder ao formato :format.',
    'decimal'              => 'O campo :attribute deve ter :decimal casas decimais.',
    'declined'             => 'O campo :attribute deve ser recusado.',
    'declined_if'          => 'O campo :attribute deve ser recusado quando :other for :value.',
    'different'            => 'Os campos :attribute e :other devem ser diferentes.',
    'digits'               => 'O campo :attribute deve ter os dígitos :digits.',
    'digits_between'       => 'O campo :attribute deve estar entre os dígitos :min e :max.',
    'dimensions'           => 'O campo :attribute tem dimensões de imagem inválidas.',
    'distinct'             => 'O campo :attribute tem um valor duplicado.',
    'doesnt_end_with'      => 'O campo :attribute não deve terminar com um dos seguintes caracteres: :values.',
    'doesnt_start_with'    => 'O campo :attribute não deve começar com um dos seguintes caracteres: :values.',
    'email'                => 'O campo :attribute deve ser um endereço de e-mail válido.',
    'ends_with'            => 'O campo :attribute deve terminar com um dos seguintes: :values.',
    'enum'                 => 'O :attribute selecionado é inválido.',
    'exists'               => 'O :attribute selecionado é inválido.',
    'file'                 => 'O campo :attribute deve ser um arquivo.',
    'filled'               => 'O campo :attribute deve ter um valor.',
    'gt'                   => [
        'array'   => 'O campo :attribute deve ter mais de :value itens.',
        'file'    => 'O campo :attribute deve ser maior que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior que :value.',
        'string'  => 'O campo :attribute deve ser maior que :value caracteres.',
    ],
    'gte'                  => [
        'array'   => 'O campo :attribute deve ter :value itens ou mais.',
        'file'    => 'O campo :attribute deve ser maior ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior ou igual a :value.',
        'string'  => 'O campo :attribute deve ser maior ou igual aos caracteres :value.',
    ],
    'image'                => 'O campo :attribute deve ser uma imagem.',
    'in'                   => 'O :attribute selecionado é inválido.',
    'in_array'             => 'O campo :attribute deve existir em :other.',
    'integer'              => 'O campo :attribute deve ser um número inteiro.',
    'ip'                   => 'O campo :attribute deve ser um endereço IP válido.',
    'ipv4'                 => 'O campo :attribute deve ser um endereço IPv4 válido.',
    'ipv6'                 => 'O campo :attribute deve ser um endereço IPv6 válido.',
    'json'                 => 'O campo :attribute deve ser uma cadeia de caracteres JSON válida.',
    'lowercase'            => 'O campo :attribute deve estar em letras minúsculas.',
    'lt'                   => [
        'array'   => 'O campo :attribute deve ter menos de :value itens.',
        'file'    => 'O campo :attribute deve ser menor que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor que :value.',
        'string'  => 'O campo :attribute deve ter menos de :value caracteres.',
    ],
    'lte'                  => [
        'array'   => 'O campo :attribute não deve ter mais do que :value itens.',
        'file'    => 'O campo :attribute deve ser menor ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor ou igual a :value.',
        'string'  => 'O campo :attribute deve ser menor ou igual a :value caracteres.',
    ],
    'mac_address'          => 'O campo :attribute deve ser um endereço MAC válido.',
    'max'                  => [
        'array'   => 'O campo :attribute não deve ter mais do que :max itens.',
        'file'    => 'O campo :attribute não deve ser maior que :max kilobytes.',
        'numeric' => 'O campo :attribute não deve ser maior que :max.',
        'string'  => 'O campo :attribute não deve ser maior que :max caracteres.',
    ],
    'max_digits'           => 'O campo :attribute não deve ter mais de :max dígitos.',
    'mimes'                => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'mimetypes'            => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'min'                  => [
        'array'   => 'O campo :attribute deve ter pelo menos :min itens.',
        'file'    => 'O campo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser, no mínimo, :min.',
        'string'  => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'min_digits'           => 'O campo :attribute deve ter pelo menos :min dígitos.',
    'missing'              => 'O campo :attribute deve estar ausente.',
    'missing_if'           => 'O campo :attribute deve estar ausente quando :other for :value.',
    'missing_unless'       => 'O campo :attribute deve estar ausente, a menos que :other seja :value.',
    'missing_with'         => 'O campo :attribute deve estar ausente quando :values estiver presente.',
    'missing_with_all'     => 'O campo :attribute deve estar ausente quando :values estiver presente.',
    'multiple_of'          => 'O campo :attribute deve ser um múltiplo de :value.',
    'not_in'               => 'O :attribute selecionado é inválido.',
    'not_regex'            => 'O formato do campo :attribute é inválido.',
    'numeric'              => 'O campo :attribute deve ser um número.',
    'password'             => [
        'letters'       => 'O campo :attribute deve conter pelo menos uma letra.',
        'mixed'         => 'O campo :attribute deve conter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers'       => 'O campo :attribute deve conter pelo menos um número.',
        'symbols'       => 'O campo :attribute deve conter pelo menos um símbolo.',
        'uncompromised' => 'O dado :attribute apareceu em um vazamento de dados. Por favor, escolha um :attribute diferente.',
    ],
    'present'              => 'O campo :attribute deve estar presente.',
    'prohibited'           => 'O campo :attribute é proibido.',
    'prohibited_if'        => 'O campo :attribute é proibido quando :other é :value.',
    'prohibited_unless'    => 'O campo :attribute é proibido, a menos que :other esteja em :values.',
    'prohibits'            => 'O campo :attribute proíbe a presença de :other.',
    'regex'                => 'O formato do campo :attribute é inválido.',
    'required'             => 'O campo :attribute é obrigatório.',
    'required_array_keys'  => 'O campo :attribute deve conter entradas para: :values.',
    'required_if'          => 'O campo :attribute é obrigatório quando :other é :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other é aceito.',
    'required_unless'      => 'O campo :attribute é obrigatório, a menos que :other esteja em :values.',
    'required_with'        => 'O campo :attribute é obrigatório quando :values está presente.',
    'required_with_all'    => 'O campo :attribute é obrigatório quando :values está presente.',
    'required_without'     => 'O campo :attribute é obrigatório quando :values não está presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum dos campos :values estiver presente.',
    'same'                 => 'O campo :attribute deve corresponder a :other.',
    'size'                 => [
        'array'   => 'O campo :attribute deve conter itens :size.',
        'file'    => 'O campo :attribute deve ser de :size kilobytes.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string'  => 'O campo :attribute deve ter os caracteres :size.',
    ],
    'starts_with'          => 'O campo :attribute deve começar com um dos seguintes: :values.',
    'string'               => 'O campo :attribute deve ser uma cadeia de caracteres.',
    'timezone'             => 'O campo :attribute deve ser um fuso horário válido.',
    'unique'               => 'O :attribute já foi ocupado.',
    'uploaded'             => 'O :attribute não conseguiu fazer o upload.',
    'uppercase'            => 'O campo :attribute deve estar em letras maiúsculas.',
    'url'                  => 'O campo :attribute deve ser um URL válido.',
    'ulid'                 => 'O campo :attribute deve ser um ULID válido.',
    'uuid'                 => 'O campo :attribute deve ser um UUID válido.',
    'custom'               => [
        'attribute-name' => [
            'rule-name' => 'mensagem personalizada',
        ],
    ],
];
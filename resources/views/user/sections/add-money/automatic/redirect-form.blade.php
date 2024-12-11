<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Redirecting ...</title>
</head>
<body>
    <form action="{{ $action_url }}" method="{{ $form_method }}" id="request-form">
        @foreach ($redirect_form_data as $input)
            <input type="hidden" name="{{ $input->name }}" value="{{ $input->value }}">
        @endforeach
    </form>

    <script>
        let requestForm = document.querySelector("#request-form");
        requestForm.submit();
    </script>
</body>
</html>

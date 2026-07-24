<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cashflows Switch Notice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            line-height: 1.7;
            color: #f5f5f5;
            background-color: #262626;
            margin: 30px;
        }
        .border-box {
            border: 1px solid #999999;
            padding: 40px;
            min-height: 600px;
        }
        .logo {
            width: 130px;
            margin-bottom: 40px;
        }
        p {
            margin: 0 0 22px 0;
        }
    </style>
</head>
<body>
    <div class="border-box">
        @if($logo_data_uri)
            <img class="logo" src="{{ $logo_data_uri }}" alt="Logo">
        @endif

        <p>FAO of Cashflows,</p>

        <p>
            Please accept this as formal notice that we, {{ $account_name }}, are requesting to move away
            from Cashflows&rsquo;s services and move over to Cardstream.
        </p>

        <p>Kind regards,<br>{{ $recipient_name }}</p>
    </div>
</body>
</html>

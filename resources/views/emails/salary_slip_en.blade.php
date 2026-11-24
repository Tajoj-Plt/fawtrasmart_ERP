<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip</title>
</head>
<body style="direction: ltr; text-align: left; font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">

    <table align="center" width="600" style="background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);">
        <tr>
            <td>
                <h2 style="color: #333; text-align: center;">Your Salary Slip for Period from {{ $details['from'] }} to {{ $details['to'] }}</h2>

                <p style="font-size: 16px; color: #555; line-height: 1.8;">Dear <strong>{{ $details['name'] }}</strong>,</p>
                <p style="font-size: 16px;">I hope this message finds you well. Below are your salary details:</p>

                <p style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 18px;">
                    <strong>Payment Date:</strong> {{ $details['create'] }}
                </p>

                <p style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 18px;">
                    <strong>Gross Salary:</strong> {{ number_format($details['total_salary'], 2) }} SAR
                </p>

                <p style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 18px;">
                    <strong>Deductions:</strong> {{ number_format($details['total_deductions'], 2) }} SAR
                </p>

                <p style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 18px;">
                    <strong>Net Salary:</strong> {{ number_format($details['net_salary'], 2) }} SAR
                </p>

                <p style="font-size: 16px;">Thank you for your continuous effort and dedication at work.</p>

                <div style="margin-top: 20px; font-size: 14px; color: #888; text-align: center;">
                    <p>Best regards,</p>
                    <p>Human Resources Department</p>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
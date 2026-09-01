<?php
// templates/manage_affiliates.php

// جلوگیری از دسترسی مستقیم — این فایل فقط از طریق روتر index.php (با check_auth) لود می‌شود
if (!defined('MNAFF_PANEL')) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

require_once __DIR__ . '/layouts/header.php';

   if (!empty($message)) {  
       ?>
        <div class="alert alert-primary" role="alert">
            <?php echo $message; ?>
        </div>
<?php } 
            
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card p-4 form-section">
            <h2 class="text-center mb-4">تعریف بازاریاب جدید</h2>

            <form id="affiliate-form" action="/mnaffiliate/dashboard" method="POST">
                <div class="mb-3 position-relative">
                    <label for="user-search-input" class="form-label">جستجوی کاربر (شماره موبایل):</label>
                    <input type="text" class="form-control" id="user-search-input" placeholder="شماره موبایل را وارد کنید">
                    <ul id="user-list" class="list-unstyled card p-2 mt-1"></ul>
                </div>

                <div class="mb-3">
                    <label for="selected-user" class="form-label">کاربر انتخاب‌شده:</label>
                    <input type="text" class="form-control" id="selected-user" readonly>
                    <input type="hidden" name="wp_user_id" id="wp-user-id" required>
                    <input type="hidden" name="user_mobile" id="user-mobile-hidden" required>
                </div>

                <div class="mb-3">
                    <label for="commission_rate" class="form-label">نرخ کمیسیون بازاریاب (%):</label>
                    <input type="number" class="form-control" name="commission_rate" id="commission_rate" value="5" step="0.01" required>
                </div>
                
                <div class="mb-3">
                    <label for="referred_user_commission_rate" class="form-label">نرخ کمیسیون کاربر ارجاعی (%):</label>
                    <input type="number" class="form-control" name="referred_user_commission_rate" id="referred_user_commission_rate" value="5" step="0.01" required>
                    <div class="form-text">
                        مجموع نرخ کمیسیون بازاریاب و کاربر ارجاعی نباید از ۱۰٪ بیشتر باشد.
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-block">تعریف به عنوان بازاریاب</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
    $(document).ready(function() {
        // جستجو از طریق پروکسی سمت سرور پنل — کلید API وردپرس هرگز به مرورگر نمی‌رود
        const searchUrl = '/mnaffiliate/api/users-search';
        
        let typingTimer;
        const doneTypingInterval = 500; // 500 milliseconds = 0.5 seconds
        
        // Search users with AJAX
        $('#user-search-input').on('keyup', function() {
            
                clearTimeout(typingTimer);
                
                const query = $(this).val().trim();
            
                if (query.length < 3) {
                    $('#user-list').hide().empty();
                    return;
                }
                typingTimer = setTimeout(function() {
                    $.ajax({
                        url: searchUrl,
                        data: { phone: query },
                        success: function(response) {
                            $('#user-list').empty();
                            const users = response.status === 'success' ? response.users : [];
                            if (users.length > 0) {
                                users.forEach(user => {
                                    const li = $('<li>').text(`${user.name} (${user.phone})`).attr('data-user-id', user.id);
                                    $('#user-list').append(li);
                                });
                                $('#user-list').show();
                            } else {
                                $('#user-list').html('<li>کاربری یافت نشد.</li>').show();
                            }
                        },
                        error: function() {
                            $('#user-list').html('<li>خطایی در اتصال رخ داد.</li>').show();
                        }
                    });
                }, doneTypingInterval);  
        });

        // Select a user from the list
        $('#user-list').on('click', 'li', function() {
            const userId = $(this).data('userId');
            const userName = $(this).text();
            const mobileMatch = userName.match(/\(([^)]+)\)/);
            const userMobile = mobileMatch ? mobileMatch[1] : '';

            $('#selected-user').val(userName);
            $('#wp-user-id').val(userId);
            $('#user-mobile-hidden').val(userMobile); // NEW: Set the mobile number
            
            $('#user-list').hide();
        });

        // Submit form with AJAX
        // $('#affiliate-form').on('submit', function(e) {
        //     e.preventDefault(); // Prevents default form submission

        //     $.ajax({
        //         url: 'index.php',
        //         type: 'POST',
        //         data: $(this).serialize(),
        //         success: function(response) {
        //             // Assuming response is a JSON object with 'status' and 'message'
        //             // For now, it's just the HTML of the entire page, so let's log it
        //             console.log('Form submitted successfully. Response:', response);
                    
        //             // You can add logic here to display a success message to the user.
        //             // Example: $('#form-messages').html('<div class="alert alert-success">بازاریاب با موفقیت ثبت شد!</div>');
        //         },
        //         error: function(jqXHR, textStatus, errorThrown) {
        //             console.error('AJAX Error:', textStatus, errorThrown);
        //             // Display error message to the user
        //             // Example: $('#form-messages').html('<div class="alert alert-danger">خطایی در ثبت بازاریاب رخ داد.</div>');
        //         }
        //     });
        // });
    });
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
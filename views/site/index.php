<div class="container-fluid">
    <div class="row">
        <div class="card">
            <ul>
                <li>
                    <p><b>Api called by Rules Engine</b></p>
                    <?= $_SERVER['SERVER_NAME'] ?>/index.php?r=V1
                </li>

                <li>
                    <p></p>
                    <p><b>Api called by Woocommerce plugin</b></p>
                    <?= $_SERVER['SERVER_NAME'] ?>/index.php?r=webhook/woocommerce
                </li>
            </ul>
        </div>
    </div>
</div>
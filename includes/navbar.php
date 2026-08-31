<nav class="navbar navbar-expand-lg bg-white border-bottom">

    <div class="container-fluid">

        <button
            class="btn btn-outline-secondary d-md-none"
            id="sidebarToggle">

            <i class="bi bi-list"></i>

        </button>

        <span class="navbar-brand ms-2 fw-bold">
            Uttara Mobile
        </span>

        <div class="ms-auto">

            <div class="dropdown">

                <button
                    class="btn btn-light dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown">

                    <i class="bi bi-person-circle"></i>

                    <?php echo htmlspecialchars($_SESSION["admin_username"]); ?>

                </button>

                <ul class="dropdown-menu dropdown-menu-end">

                    <li>
                        <a
                            class="dropdown-item"
                            href="<?php echo $basePath; ?>logout.php">

                            <i class="bi bi-box-arrow-right"></i>
                            Logout

                        </a>
                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>
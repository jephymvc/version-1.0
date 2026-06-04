<?php
/* Smarty version 4.5.6, created on 2026-06-03 11:50:03
  from 'C:\xampp\htdocs\jephy\version-1.0\src\jephy-mvc\app\views\errors\404.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.6',
  'unifunc' => 'content_6a1ff8cb1a0903_63605734',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd48fc5e54fc83c8e37db1c087f957da521ecf68c' => 
    array (
      0 => 'C:\\xampp\\htdocs\\jephy\\version-1.0\\src\\jephy-mvc\\app\\views\\errors\\404.tpl',
      1 => 1772700208,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6a1ff8cb1a0903_63605734 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_loadInheritance();
$_smarty_tpl->inheritance->init($_smarty_tpl, true);
?>


<?php 
$_smarty_tpl->inheritance->instanceBlock($_smarty_tpl, 'Block_3252095806a1ff8cb19dc08_56074915', "styles");
?>

<?php 
$_smarty_tpl->inheritance->instanceBlock($_smarty_tpl, 'Block_15055333766a1ff8cb19fd45_56564437', "home");
$_smarty_tpl->inheritance->endChild($_smarty_tpl, "layout.tpl");
}
/* {block "styles"} */
class Block_3252095806a1ff8cb19dc08_56074915 extends Smarty_Internal_Block
{
public $subBlocks = array (
  'styles' => 
  array (
    0 => 'Block_3252095806a1ff8cb19dc08_56074915',
  ),
);
public function callBlock(Smarty_Internal_Template $_smarty_tpl) {
?>

<style>
#home-slide {
    background-color: var(--color-primary);
    display: block;
    width: 100%;
    min-height: 350px;
    background-image: url(/assets/images/banners/slide.jpg);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
    top: 0;
    left: 0;
}
</style>
<?php
}
}
/* {/block "styles"} */
/* {block "home"} */
class Block_15055333766a1ff8cb19fd45_56564437 extends Smarty_Internal_Block
{
public $subBlocks = array (
  'home' => 
  array (
    0 => 'Block_15055333766a1ff8cb19fd45_56564437',
  ),
);
public function callBlock(Smarty_Internal_Template $_smarty_tpl) {
?>

<section id="home-slide">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-9">
				<div style="display: flex; align-items: center; flex-direction: column; width: 100%; height: 350px; ">
					<div style="border: 0px solid #f00; margin: auto 0px;">					
						
						<div>
						
							<div class="home-slide-badges">
								<span class="home-slide-top-badge">
									<span class="badge-bull"></span>
									Page Not Found
								</span>
							</div>
							
							<h1 class="home-slide-main-heading">
								404 Error: Page Not Found!
							</h1>
							
							<h3 class="home-slide-sub-heading">
								The page you seek has been temporaily moved.
							</h3>
							
						</div>
						
						<div class="home-slide-ctas">
							<a href="/">Go back to home page</a>
						</div>
						
					</div>
				</div>
				
			</div>
			
		</div>
	</div>
</section>
<?php
}
}
/* {/block "home"} */
}

<?php
/* Smarty version 4.5.6, created on 2026-05-18 15:23:53
  from 'C:\xampp\htdocs\jephy\version-1.0\src\jephy-mvc\app\views\home\documentation.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.6',
  'unifunc' => 'content_6a0b12e994b1d7_20494889',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'c86cf29a11660f4ea7e8e6bda6093f89938f1e95' => 
    array (
      0 => 'C:\\xampp\\htdocs\\jephy\\version-1.0\\src\\jephy-mvc\\app\\views\\home\\documentation.tpl',
      1 => 1777063018,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:partials/header.tpl' => 1,
  ),
),false)) {
function content_6a0b12e994b1d7_20494889 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_loadInheritance();
$_smarty_tpl->inheritance->init($_smarty_tpl, true);
?>


<?php 
$_smarty_tpl->inheritance->instanceBlock($_smarty_tpl, 'Block_17340132846a0b12e9928c57_54626499', "styles");
?>


<?php 
$_smarty_tpl->inheritance->instanceBlock($_smarty_tpl, 'Block_7256602956a0b12e992dc20_82429759', "header");
?>


<?php 
$_smarty_tpl->inheritance->instanceBlock($_smarty_tpl, 'Block_13683540096a0b12e9946027_04980494', "home");
$_smarty_tpl->inheritance->endChild($_smarty_tpl, "layout.tpl");
}
/* {block "styles"} */
class Block_17340132846a0b12e9928c57_54626499 extends Smarty_Internal_Block
{
public $subBlocks = array (
  'styles' => 
  array (
    0 => 'Block_17340132846a0b12e9928c57_54626499',
  ),
);
public function callBlock(Smarty_Internal_Template $_smarty_tpl) {
?>

<style>
	#home-slide {
		height: 280px !important;
		min-height: 280px !important;		
	}
	
	
</style>
<?php
}
}
/* {/block "styles"} */
/* {block "header"} */
class Block_7256602956a0b12e992dc20_82429759 extends Smarty_Internal_Block
{
public $subBlocks = array (
  'header' => 
  array (
    0 => 'Block_7256602956a0b12e992dc20_82429759',
  ),
);
public function callBlock(Smarty_Internal_Template $_smarty_tpl) {
?>

	<?php $_smarty_tpl->_subTemplateRender("file:partials/header.tpl", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, false);
}
}
/* {/block "header"} */
/* {block "home"} */
class Block_13683540096a0b12e9946027_04980494 extends Smarty_Internal_Block
{
public $subBlocks = array (
  'home' => 
  array (
    0 => 'Block_13683540096a0b12e9946027_04980494',
  ),
);
public function callBlock(Smarty_Internal_Template $_smarty_tpl) {
?>

<section id="home-slide">
	<div class="container">
		<div class="row align-items-center justify-content-center">
			<div class="col-md-9 col-sm-12">
				<div style="display: flex; align-items: flex-end; flex-direction: column; width: 100%; height: 280px; ">
					<div style="border: 0px solid #f00; margin: auto 0px;">						
						<div>							
							<h1 class="home-slide-main-heading centered-texts">
								Documentation
							</h1>							
							<h3 class="home-slide-sub-heading centered-texts">
								The modern PHP framework that provides elegant syntax, 
								robust tools, and a delightful developer experience.
								
								Get started in minutes.
							</h3>							
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

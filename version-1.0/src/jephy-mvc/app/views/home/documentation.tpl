{extends file="layout.tpl"}

{block name="styles"}
<style>
	#home-slide {
		height: 280px !important;
		min-height: 280px !important;		
	}
	
	
</style>
{/block}

{block name="header"}
	{include file="partials/header.tpl"}
{/block}

{block name="home"}
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

{/block}